<?php

namespace App\Services\InstanceService;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Instance;
use App\Models\Season;
use App\Models\StaffCoaching;
use App\Models\User;
use App\Repositories\Competition\CompetitionSeasonRepository;
use App\Services\CompetitionService\CompetitionService;
use App\Services\InstanceService\InstanceData\InitialSeed;
use App\Services\PersonService\PersonConfig\PersonTypes;
use App\Services\PersonService\PersonService;
use App\Support\GameContext;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CreateInstance
{
    private Instance $instance;

    private CompetitionService $competitionService;

    private Season $season;

    private PersonService $personService;

    private CompetitionSeasonRepository $competitionRepository;

    const FREE_AGENTS_COUNT = 200;

    const FREE_AGENTS_POTENTIAL_LIMIT = 150;

    const FREE_STAFF_PER_CLUB = 5;

    public function __construct(
        CompetitionService $competitionService,
        PersonService $personService,
        CompetitionSeasonRepository $competitionRepository,
    ) {
        $this->competitionService = $competitionService;
        $this->personService = $personService;
        $this->competitionRepository = $competitionRepository;
    }

    public function instanceInit(User $user): Instance
    {
        $init = new InitialSeed;
        // @todo select a club
        $this->storeInstance($user->id, null, null, null);
        $init->seedFromBaseTables($this->instance->id);
        $this->startFirstSeason();
        $this->instance->season_id = $this->season->id;
        $this->instance->club_id = Club::query()
            ->forInstance($this->instance->id)
            ->where('base_club_id', 1)
            ->firstOrFail()->id;
        $this->instance->save();
        app(GameContext::class)->set($this->instance->id, $this->season->id, (string) $this->instance->instance_date);
        $this->mapInitialCompetitionsToSeasonsWithClubs();
        $this->setCompetitionsForTheFirstSeason();
        $this->assignPeopleToClubs();
        $this->assignUserManager($user);
        $this->generateFreeAgents();

        return $this->instance;
    }

    private function assignUserManager(User $user): void
    {
        $manager = StaffCoaching::query()
            ->forInstance($this->instance->id)
            ->where('club_id', $this->instance->club_id)
            ->where('type', PersonTypes::MANAGER)
            ->firstOrFail();

        foreach ([
            'attacking', 'defending', 'fitness', 'mental', 'tactical', 'technical',
            'working_with_youngsters', 'adaptability', 'determination', 'discipline',
            'man_management', 'motivating', 'judging_player_potential', 'judging_player_ability',
            'judging_staff_ability', 'negotiating', 'tactics', 'distribution', 'handling', 'shot_stopping',
        ] as $attribute) {
            $manager->{$attribute} = 1;
        }

        $manager->save();

        $nameParts = explode(' ', trim($user->name), 2);
        $person = $manager->person;
        $person->first_name = $nameParts[0];
        $person->last_name = $nameParts[1] ?? '';
        $person->save();

        $this->instance->manager_id = $manager->id;
        $this->instance->save();
    }

    protected function storeInstance(int $userId, ?int $managerId, ?int $seasonId, ?int $clubId): Instance
    {
        $this->instance = new Instance;

        $this->instance->user_id = $userId;
        $this->instance->manager_id = $managerId;
        $this->instance->season_id = $seasonId;
        $this->instance->club_id = $clubId;
        $this->instance->instance_date = $this->initialInstanceDate();
        $this->instance->instance_hash = (string) Str::uuid();

        $this->instance->save();
        app(GameContext::class)->setInstanceId($this->instance->id);

        return $this->instance;
    }

    public function startFirstSeason(): Season
    {
        $this->season = new Season;
        $firstSeasonStartDate = $this->firstSeasonStartDate();
        $firstSeasonEndDate = $firstSeasonStartDate->copy()->add('1 year');

        $this->season->instance_id = $this->instance->id;
        $this->season->start_date = $firstSeasonStartDate;
        $this->season->end_date = $firstSeasonEndDate;

        $this->season->save();

        return $this->season;
    }

    private function initialInstanceDate(): Carbon
    {
        return Carbon::create((int) date('Y'), 7, 1)->startOfDay();
    }

    private function firstSeasonStartDate(): Carbon
    {
        return Carbon::create((int) date('Y'), 8, 15)->startOfDay();
    }

    public function mapInitialCompetitionsToSeasonsWithClubs()
    {
        $this->competitionRepository->storeInitial($this->instance->id, $this->season->id);
    }

    public function setCompetitionsForTheFirstSeason(): void
    {
        $competitions = Competition::query()
            ->where('instance_id', $this->instance->id)
            ->orderBy('id')
            ->get();

        foreach ($competitions as $competition) {
            $clubIds = $this->competitionRepository->clubIds(
                $competition->id,
                $this->season->id,
                $this->instance->id
            );

            if ($clubIds === []) {
                continue;
            }

            if ($competition->type === 'league') {
                $this->competitionService->makeLeague(
                    $clubIds,
                    $competition->id,
                    $this->season->id,
                    $this->instance->id
                );

                continue;
            }

            if ($competition->type !== 'tournament') {
                continue;
            }

            $clubs = Club::query()
                ->forInstance($this->instance->id)
                ->whereIn('id', $clubIds)
                ->get()
                ->sortBy(fn (Club $club): int => array_search($club->id, $clubIds, true))
                ->values();

            if ((int) $competition->groups === 1) {
                $this->competitionService->makeTournamentGroupStage(
                    $clubs,
                    $competition->id,
                    $this->season->id,
                    $this->instance->id
                );

                continue;
            }

            $this->competitionService->makeTournament(
                $clubs,
                $competition->id,
                $this->season->id,
                $this->instance->id
            );
        }
    }

    public function assignPeopleToClubs(): void
    {
        $clubs = Club::query()
            ->forInstance($this->instance->id)
            ->get();

        foreach ($clubs as $club) {
            $this->personService->createPlayersForClub($club);
            $this->personService->initialStaffClubSeed($club);
        }

        $this->personService->generateFreeStaff($clubs->count() * self::FREE_STAFF_PER_CLUB);
    }

    public function generateFreeAgents()
    {
        $this->personService->createFreePlayers(self::FREE_AGENTS_COUNT);
    }
}
