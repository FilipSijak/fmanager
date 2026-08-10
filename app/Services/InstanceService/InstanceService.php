<?php

namespace App\Services\InstanceService;

use App\Events\NextDay;
use App\Events\SeasonCompleted;
use App\Events\SeasonStarted;
use App\Models\Instance;
use App\Models\Season;
use App\Repositories\CompetitionRepository;
use App\Services\GameService\CompleteGameService;
use App\Services\GameService\MatchSimulationEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InstanceService implements IInstanceService
{
    private CompetitionRepository $competitionRepository;

    private Season $season;

    private MatchSimulationEngine $matchSimulationEngine;

    private CompleteGameService $completeGameService;

    private CreateInstance $createInstance;

    private ?Instance $instance = null;

    public function __construct(
        CompetitionRepository $competitionRepository,
        CreateInstance $createInstance,
        MatchSimulationEngine $matchSimulationEngine,
        CompleteGameService $completeGameService

    ) {
        $this->competitionRepository = $competitionRepository;
        $this->matchSimulationEngine = $matchSimulationEngine;
        $this->completeGameService = $completeGameService;
        $this->createInstance = $createInstance;
    }

    private function getInstance(): Instance
    {
        if ($this->instance === null) {
            $this->instance = Instance::find(1);
        }

        return $this->instance;
    }

    public function createNewInstance(): bool|Instance
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
        $this->season = Season::findOrFail($instance->season_id);
        $currentGameDate = Carbon::parse($instance->instance_date);

        event(new NextDay($instance));

        if ($currentGameDate->month === 6 && $currentGameDate->day === 15) {
            event(new SeasonCompleted($instance));
        }

        if ($currentGameDate->month === 6 && $currentGameDate->day === 16) {
            event(new SeasonStarted($instance));
        }

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
        $instance = $this->getInstance();
        $games = $this->competitionRepository->getScheduledGames($instance);

        foreach ($games as $game) {
            $result = $this->matchSimulationEngine->simulate($game);
            $this->completeGameService->complete(
                $game->id,
                $result->homeGoals,
                $result->awayGoals,
                $result->summary
            );
        }
    }

    public function setSeason(Season $season)
    {
        $this->season = $season;
    }
}
