<?php

namespace App\Services\InstanceService;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Instance;
use App\Models\Player;
use App\Models\Season;
use App\Models\StaffCoaching;
use App\Models\StaffPhysio;
use App\Models\StaffScout;
use App\Repositories\CompetitionRepository;
use App\Repositories\PlayerRepository;
use App\Services\CompetitionService\CompetitionService;
use App\Services\InstanceService\InstanceData\InitialSeed;
use App\Services\PersonService\GeneratePeople\PlayerPotential;
use App\Services\PersonService\GeneratePeople\StaffPotential;
use App\Services\PersonService\PersonConfig\PersonTypes;
use App\Services\PersonService\PersonService;
use Carbon\Carbon;
use Faker\Factory as FakerFactory;
use Illuminate\Support\Facades\DB;

class CreateInstance
{
    private Instance $instance;

    private PlayerRepository $playerRepository;

    private CompetitionService $competitionService;

    private Season $season;

    private PersonService $personService;

    private CompetitionRepository $competitionRepository;

    private PlayerPotential $playerPotentialGenerator;

    private StaffPotential $staffPotentialGenerator;

    const FREE_AGENTS_COUNT = 200;

    const FREE_AGENTS_POTENTIAL_LIMIT = 150;

    public function __construct(
        CompetitionService $competitionService,
        PersonService $personService,
        CompetitionRepository $competitionRepository,
        PlayerPotential $playerPotential,
        StaffPotential $staffPotential,
        PlayerRepository $playerRepository,
    ) {
        $this->competitionService = $competitionService;
        $this->personService = $personService;
        $this->competitionRepository = $competitionRepository;
        $this->playerPotentialGenerator = $playerPotential;
        $this->staffPotentialGenerator = $staffPotential;
        $this->playerRepository = $playerRepository;
    }

    public function instanceInit(): Instance
    {
        $init = new InitialSeed;
        // @todo create user and select club
        $this->storeInstance(1, 1, 1, 1);
        $init->seedFromBaseTables($this->instance->id);
        $this->startFirstSeason();
        $this->mapInitialCompetitionsToSeasonsWithClubs();
        $this->setCompetitionsForTheFirstSeason();
        $this->assignPeopleToClubs();
        $this->generateFreeAgents();

        return $this->instance;
    }

    protected function storeInstance(int $userId, int $managerId, int $seasonId, int $clubId): Instance
    {
        $this->instance = new Instance;

        $this->instance->user_id = $userId;
        $this->instance->manager_id = $managerId;
        $this->instance->season_id = $seasonId;
        $this->instance->club_id = $clubId;
        $this->instance->instance_date = $this->initialInstanceDate();
        $this->instance->instance_hash = uniqid();

        $this->instance->save();

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
        $this->competitionRepository->setCompetitionsSeasons($this->instance->id, $this->season->id);
    }

    public function setCompetitionsForTheFirstSeason(): void
    {
        $competitions = Competition::query()
            ->where('instance_id', $this->instance->id)
            ->orderBy('id')
            ->get();

        foreach ($competitions as $competition) {
            $clubIds = $this->competitionRepository->clubIdsForCompetitionSeason(
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
            $this->assignPlayersToClubs($club);
            $this->assignStaffToClubs($club);
        }
    }

    public function assignPlayersToClubs(Club $club): void
    {
        $academyRank = $club->rank_academy;
        $playerPotentialList = $this->playerPotentialGenerator->getPlayerPotential($academyRank);
        $playersPotentialWithPosition = $this->playerPotentialGenerator->assignPlayerPositions($playerPotentialList);
        $generatedPlayers = [];

        foreach ($playersPotentialWithPosition as $playerPotential) {
            $player = $this->personService->createPerson(
                $playerPotential,
                $this->instance->id,
                PersonTypes::PLAYER
            );

            $generatedPlayers[] = $player;
        }

        $this->playerRepository->bulkPlayerInsert($this->instance->id, $club, $generatedPlayers);
        $players = Player::where('club_id', $club->id)->get();
        $this->playerRepository->bulkAssignmentPlayersPositions($players);
    }

    private function assignStaffToClubs(Club $club): void
    {
        $staffMembers = $this->staffPotentialGenerator->getStaffPotentialAndRole($club->rank_training);
        $faker = FakerFactory::create();

        DB::transaction(function () use ($club, $staffMembers, $faker): void {
            foreach ($staffMembers as $staffMember) {
                $personId = DB::table('people')->insertGetId([
                    'instance_id' => $this->instance->id,
                    'first_name' => $faker->firstNameMale,
                    'last_name' => $faker->lastName,
                    'dob' => $faker->dateTimeBetween('-65 years', '-28 years')->format('Y-m-d'),
                    'country_code' => $faker->countryCode,
                ]);
                $attributes = fn (array $names): array => array_combine($names,
                    array_map(fn (): int => $this->staffAttribute($staffMember->potential), $names));

                if (in_array($staffMember->role, [PersonTypes::MANAGER, PersonTypes::ASSISTANT_MANAGER,
                    PersonTypes::COACH, PersonTypes::YOUTH_COACH], true)) {
                    StaffCoaching::forceCreate(array_merge([
                        'instance_id' => $this->instance->id, 'person_id' => $personId, 'club_id' => $club->id,
                        'type' => $staffMember->role, 'coaching_potential' => $staffMember->potential,
                        'mental_potential' => $staffMember->potential, 'goalkeeping_potential' => $staffMember->potential,
                        'knowledge_potential' => $staffMember->potential,
                    ], $attributes(['attacking', 'defending', 'fitness', 'mental', 'tactical', 'technical',
                        'working_with_youngsters', 'adaptability', 'determination', 'discipline', 'man_management',
                        'motivating', 'judging_player_potential', 'judging_player_ability', 'judging_staff_ability',
                        'negotiating', 'tactics', 'distribution', 'handling', 'shot_stopping'])));

                    continue;
                }

                if ($staffMember->role === PersonTypes::SCOUT) {
                    StaffScout::forceCreate(array_merge(['instance_id' => $this->instance->id,
                        'person_id' => $personId, 'club_id' => $club->id],
                        $attributes(['judging_player_ability', 'judging_player_potential', 'tactical_knowledge',
                            'data_analysis', 'market_knowledge'])));

                    continue;
                }

                StaffPhysio::forceCreate(array_merge(['instance_id' => $this->instance->id,
                    'person_id' => $personId, 'club_id' => $club->id,
                    'team_type' => $staffMember->role === 'YOUTH_PHYSIO' ? 'YOUTH_TEAM' : 'FIRST_TEAM'],
                    $attributes(['physiotherapy', 'injury_prevention', 'rehabilitation', 'sports_science',
                        'fitness_assessment'])));
            }
        });
    }

    private function staffAttribute(int $potential): int
    {
        $ability = (int) round($potential / 10);

        return rand(max(1, $ability - 3), min(20, $ability + 2));
    }

    public function generateFreeAgents()
    {
        $generatedPlayers = [];

        for ($i = 0; $i < self::FREE_AGENTS_COUNT; $i++) {
            $playerWithPositionAndPotential = $this->playerPotentialGenerator->generateFreeAgent(self::FREE_AGENTS_POTENTIAL_LIMIT);

            $generatedPlayers[] = $this->personService->createPerson(
                $playerWithPositionAndPotential,
                $this->instance->id,
                PersonTypes::PLAYER
            );
        }

        $this->playerRepository->bulkPlayerInsert($this->instance->id, null, $generatedPlayers);
        $players = Player::whereNull('club_id')->get();
        $this->playerRepository->bulkAssignmentPlayersPositions($players);
    }
}
