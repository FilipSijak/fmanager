<?php

namespace App\Services\InstanceService;

use App\Models\BaseData\BaseClubs;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Instance;
use App\Models\Manager;
use App\Models\Player;
use App\Models\Season;
use App\Models\User;
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

    public function __construct(
        CompetitionService $competitionService,
        PersonService $personService,
        CompetitionRepository $competitionRepository
    )
    {
        $this->instance           = new Instance();
        $this->season             = null;
        $this->competitionService = $competitionService;
        $this->personService      = $personService;
        $this->competitionRepository = $competitionRepository;
    }

    public function instanceInit(): Instance
    {
        $init = new InitialSeed();
        $this->storeInstance(1, 1, 1);
        $init->seedFromBaseTables($this->instance->id);
        $this->startFirstSeason($this->instance->id);
        $this->mapInitialCompetitionsToSeasonsWithClubs($this->season->id);
        $this->setCompetitionsForTheFirstSeason($this->competitionService, $this->season->id, $this->instance->id);
        $this->assignPlayersToClubs($this->personService, $this->instance->id);

        return $this->instance;
    }

    public function storeInstance(int $userId, int $managerId, int $clubId): Instance
    {
        $this->instance->user_id    = $userId;
        $this->instance->manager_id = $managerId;
        $this->instance->club_id    = $clubId;
        $this->instance->instance_date = new Carbon('2023-08-20');
        $this->instance->instance_hash = uniqid();

        $this->instance->save();

        return $this->instance;
    }

    public function startFirstSeason(int $instanceId): Season
    {
        $this->season         = new Season();
        $firstSeasonStartDate = Carbon::create((int)date("Y"), 8, 15);
        $firstSeasonEndDate   = $firstSeasonStartDate->copy()->add('1 year');

        $this->season->instance_id = $instanceId;
        $this->season->start_date  = $firstSeasonStartDate;
        $this->season->end_date    = $firstSeasonEndDate;

        $this->season->save();

        return $this->season;
    }

    public function mapInitialCompetitionsToSeasonsWithClubs(int $seasonId)
    {
        $this->competitionRepository->setCompetitionsSeasons($this->instance->id, $seasonId);
    }

    public function setCompetitionsForTheFirstSeason(CompetitionService $competitionService, int $seasonId, int $instanceId)
    {
        $competitions = Competition::all()->where('instance_id', $instanceId);

        foreach ($competitions as $competition) {
            if ($competition->type == 'league' || ($competition->type == 'tournament' && $competition->groups)) {

                if ($competition->type == 'league') {
                    $baseClubs = BaseClubs::all()->where('competition_id', $competition->id);

                    if ($baseClubs->count()) {
                        try {
                            $competitionService->makeLeague($baseClubs->pluck('id')->toArray(), $competition->id, $seasonId, $instanceId);
                        } catch (\Exception $e) {

                        }
                    }
                } else {
                    // @TODO
                    // need clubs for tournaments
                    $clubs = Club::all()->where('id', '>', 16);

                    $competitionService->makeTournamentGroupStage($clubs, $competition->id, $seasonId, $instanceId);
                }
            } elseif ($competition->type == 'tournament' && !$competition->groups) {
                $clubs = Club::all()->take(16);

                if (count($clubs)) {
                    $competitionService->makeTournament($clubs, $competition->id, $seasonId, $instanceId);
                }
            }
        }
    }

    public function assignPlayersToClubs(PersonService $personService, $instanceId)
    {
        $playerPotentialClass = new PlayerPotential();
        $clubs                = Club::all();
        $playerRepository     = new PlayerRepository();

        foreach ($clubs as $club) {
            $academyRank = $club->rank_academy;

            $playerListWithInitialPotential = $playerPotentialClass->getPlayerPotentialAndInitialPosition($academyRank);
            $generatedPlayers               = [];

            foreach ($playerListWithInitialPotential as $playerPotential) {
                $player = $personService->createPerson($playerPotential, $instanceId, PersonTypes::PLAYER, $club->rank_academy);

                $generatedPlayers[] = $player;
            }

            $playerRepository->bulkPlayerInsert($instanceId, $club->id, $generatedPlayers);
            $players = Player::where('club_id', $club->id)->get();
            $playerRepository->bulkAssignmentPlayersPositions($players);
        }
    }
}
