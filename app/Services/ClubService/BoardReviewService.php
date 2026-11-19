<?php

namespace App\Services\ClubService;

use App\Models\ClubBoardReview;
use App\Models\ClubSeasonExpectation;
use App\Models\Competition;
use App\Models\Instance;
use App\Models\Season;
use App\Models\StaffCoaching;
use App\Services\ClubService\Domain\BoardReviewPolicy;
use App\Services\CompetitionService\Progression\FinalLeagueTableService;
use App\Services\NewsService\NewsItem;
use App\Services\NewsService\NewsPriority;
use App\Services\NewsService\NewsService;
use App\Services\NewsService\NewsType;
use App\Services\PersonService\PersonConfig\PersonTypes;
use App\Services\PersonService\PersonService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BoardReviewService
{
    private const MINIMUM_LEAGUE_MATCHES = 5;

    public function __construct(
        private readonly BoardReviewPolicy $policy,
        private readonly FinalLeagueTableService $tables,
        private readonly PersonService $people,
        private readonly NewsService $news,
    ) {}

    public function process(int $instanceId, int $seasonId, CarbonImmutable $date): void
    {
        DB::transaction(function () use ($instanceId, $seasonId, $date): void {
            $instance = Instance::query()->lockForUpdate()->findOrFail($instanceId);
            $season = Season::query()->forInstance($instanceId)->lockForUpdate()->findOrFail($seasonId);
            $start = CarbonImmutable::parse($season->start_date);
            $end = CarbonImmutable::parse($season->end_date);

            if ((int) $instance->season_id !== $seasonId || ! $date->isSameDay($instance->instance_date)
                || ! $this->policy->isDue($start, $end, $date)) {
                return;
            }

            $expectations = ClubSeasonExpectation::query()->forInstance($instanceId)->where('season_id', $seasonId)
                ->with('club')->orderBy('club_id')->get();
            $tables = [];

            foreach ($expectations as $expectation) {
                if (ClubBoardReview::query()->where('club_season_expectation_id', $expectation->id)
                    ->where('reviewed_on', $date->toDateString())->exists()) {
                    continue;
                }

                $table = collect();
                if ($expectation->competition_id !== null) {
                    $tables[$expectation->competition_id] ??= Competition::query()->forInstance($instanceId)
                        ->where('type', 'league')->whereKey($expectation->competition_id)->exists()
                        ? $this->tables->get($instanceId, $seasonId, $expectation->competition_id)
                        : collect();
                    $table = $tables[$expectation->competition_id];
                }

                $this->review($instance, $expectation, $table, $start, $end, $date);
            }
        }, 3);
    }

    /** @param Collection<int, object> $table */
    private function review(Instance $instance, ClubSeasonExpectation $expectation, Collection $table, CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $date): void
    {
        $manager = StaffCoaching::query()->forInstance($instance->id)->where('club_id', $expectation->club_id)
            ->where('type', PersonTypes::MANAGER)->active()->with('contract')->orderBy('id')->lockForUpdate()->first();
        $row = $table->firstWhere('club_id', $expectation->club_id);
        $position = $row === null ? null : (int) $row->position;
        $played = (int) ($row?->played ?? 0);
        $tolerance = $this->policy->effectiveTolerance($expectation->tolerance, $start, $end, $date);
        $outcome = 'retained';
        $reason = null;
        $isUserManager = $manager !== null && (int) $manager->id === (int) $instance->manager_id
            && (int) $instance->club_id === (int) $expectation->club_id;

        if ($manager === null) {
            $outcome = 'vacant';
            $reason = 'The club has no active manager.';
        } elseif ($position === null || $expectation->target_position === null || $table->count() !== $expectation->league_size) {
            $outcome = 'deferred';
            $reason = 'A complete league table and season target are not available.';
        } elseif ($played < self::MINIMUM_LEAGUE_MATCHES) {
            $outcome = 'deferred';
            $reason = 'Fewer than five league matches have been played.';
        } elseif ($manager->contract?->contract_start !== null
            && CarbonImmutable::parse($manager->contract->contract_start)->greaterThan($date->subMonthsNoOverflow(2))) {
            $outcome = 'deferred';
            $reason = 'The manager has been in charge for less than two months.';
        } elseif (! $expectation->acceptsLeaguePosition($position, $tolerance, finalReview: $date->isSameDay($end))) {
            $outcome = 'dismissed';
            $reason = 'Performance is outside the board allowance.';
            $this->people->releaseUserManager($manager);

            if ($isUserManager) {
                $instance->club_id = null;
                $instance->followed_competition_id = $expectation->competition_id;
                $instance->save();
            }
        }

        ClubBoardReview::query()->create([
            'instance_id' => $instance->id, 'club_season_expectation_id' => $expectation->id,
            'staff_coaching_id' => $manager?->id, 'reviewed_on' => $date->toDateString(),
            'position' => $position, 'played' => $played, 'effective_tolerance' => $tolerance,
            'outcome' => $outcome, 'reason' => $reason,
        ]);

        if ($isUserManager) {
            $this->news->publish(new NewsItem(
                instanceId: $instance->id,
                seasonId: $expectation->season_id,
                clubId: $outcome === 'dismissed' ? null : $expectation->club_id,
                competitionId: $outcome === 'dismissed' ? null : $expectation->competition_id,
                title: $outcome === 'dismissed' ? $expectation->club->name.' dismiss you as manager' : 'Board review: '.$expectation->club->name,
                content: $expectation->description.'. Current position: '.($position ?? 'unavailable')
                    .'; matches played: '.$played.'. The current allowance is '.$tolerance
                    .' places below the target, including an early-season allowance of '.($tolerance - $expectation->tolerance)
                    .'. '.($reason ?? 'Your performance is within the board allowance.'),
                type: NewsType::BoardReview,
                priority: NewsPriority::High,
                publishedAt: $date,
            ));
        }
    }
}
