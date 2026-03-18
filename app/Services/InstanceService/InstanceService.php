<?php

namespace App\Services\InstanceService;

use App\Events\NextDay;
use App\Models\Instance;
use App\Models\Season;
use App\Repositories\CompetitionRepository;
use App\Services\CompetitionService\CompetitionService;
use App\Services\GameService\GameService;
use App\Services\PersonService\PersonService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InstanceService implements IInstanceService
{
    private CompetitionRepository  $competitionRepository;
    private Season                 $season;
    private GameService            $gameService;
    private CompetitionService     $competitionService;
    private CreateInstance         $createInstance;
    private ?Instance $instance = null;

    public function __construct(
        CompetitionService $competitionService,
        CompetitionRepository $competitionRepository,
        CreateInstance $createInstance,
        GameService $gameService

    )
    {
        $this->competitionRepository = $competitionRepository;
        $this->gameService = $gameService;
        $this->competitionService = $competitionService;
        $this->createInstance = $createInstance;
    }

    private function getInstance(): Instance
    {
        if ($this->instance === null) {
            $this->instance = Instance::find(1);
        }

        return $this->instance;
    }

    public function createNewInstance(): bool | Instance
    {
        DB::beginTransaction();

        try {
            $instance = $this->createInstance->instanceInit();

            if ($instance) {
                DB::commit();
            }

            return $instance;
        } catch (\Exception $exception) {
            echo $exception->getMessage();

            DB::rollBack();

           return false;
        }
    }

    public function nextDay()
    {
        $instance = $this->getInstance();
        $currentGameDate = Carbon::parse($instance->instance_date);

        event(new NextDay());
        // update player training progress, morale

        // update finances

        // simulate injuries, transfers

        // every month update player value, attributes, club ranking

        // simulates only the games that are not user played and that are not already simulated while user was playing
        $this->simulateGames();

        $instance->instance_date = $currentGameDate->addDay()->format('Y-m-d');

        $instance->save();
    }

    private function simulateGames()
    {
        $gamesByCompetition = [];
        $instance = $this->getInstance();
        $games = $this->competitionRepository->getScheduledGames($instance);

        $this->gameService->simulateRound($games);

        foreach ($games as $game) {
            if (!isset($gamesByCompetition[$game->competition_id])) {
                $gamesByCompetition[$game->competition_id] = [];
            }

            $gamesByCompetition[$game->competition_id][] = $game->toArray();
        }

        // create Competition Match Updater class which will take all the matches and deal with the updates
        $this->competitionService->setInstanceId($instance->id);
        $this->competitionService->setSeason($this->season);
        $this->competitionService->competitionsRoundUpdate($gamesByCompetition);

        //update other stuff like club ranking, player injuries, news service etc.
    }

    public function setSeason(Season $season)
    {
        $this->season = $season;
    }
}
