<?php

namespace App\Services\InstanceService;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Instance;
use App\Models\Player;
use App\Models\Season;
use App\Repositories\CompetitionRepository;
use App\Repositories\PlayerRepository;
use App\Services\CompetitionService\CompetitionService;
use App\Services\InstanceService\InstanceData\InitialSeed;
use App\Services\PersonService\GeneratePeople\PlayerPotential;
use App\Services\PersonService\PersonConfig\PersonTypes;
use App\Services\PersonService\PersonService;
use Carbon\Carbon;

class CreateInstance
{
    private Instance $instance;

    private PlayerRepository $playerRepository;

    private CompetitionService $competitionService;

    private Season $season;

    private PersonService $personService;

    private CompetitionRepository $competitionRepository;

    private PlayerPotential $playerPotentialGenerator;

    const FREE_AGENTS_COUNT = 200;

    const FREE_AGENTS_POTENTIAL_LIMIT = 150;

    public function __construct(
        CompetitionService $competitionService,
        PersonService $personService,
        CompetitionRepository $competitionRepository,
        PlayerPotential $playerPotential,
        PlayerRepository $playerRepository,
    ) {
        $this->competitionService = $competitionService;
        $this->personService = $personService;
        $this->competitionRepository = $competitionRepository;
        $this->playerPotentialGenerator = $playerPotential;
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
        $this->assignPlayersToClubs();
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

    public function assignPlayersToClubs()
    {
        $clubs = Club::all();

        foreach ($clubs as $club) {
            $academyRank = $club->rank_academy;

            $playerListWithInitialPotential = $this->playerPotentialGenerator->getPlayerPotentialAndInitialPosition($academyRank);
            $generatedPlayers = [];

            foreach ($playerListWithInitialPotential as $playerPotential) {
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
