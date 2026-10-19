<?php

namespace App\Services\ManagerJobService;

use App\Models\Club;
use App\Models\Instance;
use App\Models\ManagerJobApplication;
use App\Models\StaffCoaching;
use App\Models\StaffContract;
use App\Services\NewsService\NewsItem;
use App\Services\NewsService\NewsPriority;
use App\Services\NewsService\NewsService;
use App\Services\NewsService\NewsType;
use App\Services\PersonService\GeneratePeople\StaffType\StaffSalary;
use App\Services\PersonService\PersonConfig\PersonTypes;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Clubs without a manager hire the candidate whose reputation is closest to what the club looks for.
 */
class ManagerJobService
{
    /** How far a manager's reputation may be from a club's target and still be considered. */
    public const REPUTATION_TOLERANCE = 20;

    private const CONTRACT_YEARS = 2;

    public function __construct(
        private readonly NewsService $newsService,
        private readonly StaffSalary $staffSalary,
    ) {}

    /**
     * @return Collection<int, Club>
     */
    public function vacantClubs(Instance $instance): Collection
    {
        return Club::query()
            ->forInstance($instance->id)
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('staff_coaching')
                ->whereColumn('staff_coaching.club_id', 'clubs.id')
                ->where('staff_coaching.type', PersonTypes::MANAGER)
                ->where('staff_coaching.is_retired', false))
            ->orderByDesc('rank')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, int> league competition id keyed by club id for the instance's current season
     */
    public function leagueIdsByClub(Instance $instance): array
    {
        return DB::table('competition_season')
            ->join('competitions', 'competitions.id', '=', 'competition_season.competition_id')
            ->where('competition_season.instance_id', $instance->id)
            ->where('competition_season.season_id', $instance->season_id)
            ->where('competitions.type', 'league')
            ->pluck('competition_season.competition_id', 'competition_season.club_id')
            ->map(fn ($competitionId): int => (int) $competitionId)
            ->all();
    }

    /**
     * @throws DomainException when the user cannot apply for this club
     */
    public function apply(Instance $instance, int $clubId): ManagerJobApplication
    {
        return DB::transaction(function () use ($instance, $clubId): ManagerJobApplication {
            if (! $instance->isManagerUnemployed()) {
                throw new DomainException('Only managers without a club can apply for jobs.');
            }

            if (! $this->vacantClubs($instance)->contains('id', $clubId)) {
                throw new DomainException('This club is not looking for a manager.');
            }

            $alreadyApplied = ManagerJobApplication::query()
                ->forInstance($instance->id)
                ->pending()
                ->where('club_id', $clubId)
                ->where('staff_coaching_id', $instance->manager_id)
                ->exists();

            if ($alreadyApplied) {
                throw new DomainException('You have already applied for this job.');
            }

            $application = new ManagerJobApplication;
            $application->instance_id = $instance->id;
            $application->club_id = $clubId;
            $application->staff_coaching_id = $instance->manager_id;
            $application->applied_on = $instance->instance_date;
            $application->status = ManagerJobApplicationStatus::Pending;
            $application->save();

            return $application;
        });
    }

    /**
     * Each vacant club appoints the closest qualifying candidate among free AI managers and applicants, and every
     * pending application is answered.
     */
    public function fillVacancies(Instance $instance): void
    {
        DB::transaction(function () use ($instance): void {
            foreach ($this->vacantClubs($instance) as $club) {
                $this->fillVacancy($instance, $club);
            }

            ManagerJobApplication::query()
                ->forInstance($instance->id)
                ->pending()
                ->with(['club', 'staffCoaching'])
                ->get()
                ->each(fn (ManagerJobApplication $application) => $application->staffCoaching->club_id !== null
                    ? $this->withdraw($application)
                    : $this->reject($instance, $application, "{$application->club->name} have filled their manager position."));
        });
    }

    private function fillVacancy(Instance $instance, Club $club): void
    {
        $target = $club->managerReputationTarget();
        $applications = ManagerJobApplication::query()
            ->forInstance($instance->id)
            ->pending()
            ->where('club_id', $club->id)
            ->with('staffCoaching')
            ->get();

        $applicants = $applications
            ->map(fn (ManagerJobApplication $application): StaffCoaching => $application->staffCoaching)
            ->filter(fn (StaffCoaching $manager): bool => $manager->club_id === null
                && abs($manager->reputation - $target) <= self::REPUTATION_TOLERANCE);
        $aiCandidate = $this->closestFreeAiManager($instance, $target);
        $appointed = $applicants
            ->when($aiCandidate !== null, fn ($candidates) => $candidates->push($aiCandidate))
            ->sortBy(fn (StaffCoaching $manager): array => [abs($manager->reputation - $target), -$manager->reputation, $manager->id])
            ->first();

        if ($appointed !== null) {
            $this->appoint($instance, $club, $appointed);
        }

        foreach ($applications as $application) {
            if ($appointed !== null && $application->staff_coaching_id === $appointed->id) {
                $application->status = ManagerJobApplicationStatus::Accepted;
                $application->save();

                continue;
            }

            if ($application->staffCoaching->club_id !== null) {
                $this->withdraw($application);

                continue;
            }

            $this->reject($instance, $application, abs($application->staffCoaching->reputation - $target) > self::REPUTATION_TOLERANCE
                ? "{$club->name} are looking for a manager with a reputation around {$target}."
                : "{$club->name} have appointed another manager.");
        }
    }

    private function closestFreeAiManager(Instance $instance, int $target): ?StaffCoaching
    {
        return StaffCoaching::query()
            ->forInstance($instance->id)
            ->whereNull('club_id')
            ->where('type', PersonTypes::MANAGER)
            ->active()
            ->aiControlled()
            ->whereBetween('reputation', [$target - self::REPUTATION_TOLERANCE, $target + self::REPUTATION_TOLERANCE])
            ->orderByRaw('ABS(CAST(reputation AS SIGNED) - ?)', [$target])
            ->orderByDesc('reputation')
            ->orderBy('id')
            ->first();
    }

    private function appoint(Instance $instance, Club $club, StaffCoaching $manager): void
    {
        $contract = StaffContract::query()->create([
            'contract_start' => $instance->instance_date,
            'contract_end' => Carbon::parse($instance->instance_date)->addYears(self::CONTRACT_YEARS)->toDateString(),
            'salary' => $this->staffSalary->estimatedSalaryForStaffRole(PersonTypes::MANAGER, $manager->reputation),
            'signing_fee' => null,
        ]);
        $manager->club_id = $club->id;
        $manager->contract_id = $contract->id;
        $manager->save();

        if ($manager->id !== $instance->manager_id) {
            return;
        }

        $instance->club_id = $club->id;
        $instance->followed_competition_id = $this->leagueIdsByClub($instance)[$club->id] ?? $instance->followed_competition_id;
        $instance->save();
        $this->publish($instance, "{$club->name} appoint you as manager", "The {$club->name} board have accepted your application. You start work immediately.");
    }

    private function reject(Instance $instance, ManagerJobApplication $application, string $reason): void
    {
        $application->status = ManagerJobApplicationStatus::Rejected;
        $application->save();
        $this->publish($instance, "{$application->club->name} reject your application", $reason);
    }

    /**
     * Close an application quietly because the applicant has already been appointed elsewhere.
     */
    private function withdraw(ManagerJobApplication $application): void
    {
        $application->status = ManagerJobApplicationStatus::Rejected;
        $application->save();
    }

    private function publish(Instance $instance, string $title, string $content): void
    {
        $this->newsService->publish(new NewsItem(
            instanceId: $instance->id,
            seasonId: $instance->season_id,
            clubId: null,
            competitionId: null,
            title: $title,
            content: $content,
            type: NewsType::Job,
            priority: NewsPriority::High,
            publishedAt: Carbon::parse($instance->instance_date),
        ));
    }
}
