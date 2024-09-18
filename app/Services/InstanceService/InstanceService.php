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
    private \Closure|null|Instance $instance;

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
        $this->instance = Instance::all()->where('id', 1)->first();
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
            DB::rollBack();

           return false;
        }
    }

    public function nextDay()
    {
        $currentGameDate = Carbon::parse($this->instance->instance_date);

        event(new NextDay());
        // update player training progress, morale

        // update finances

        // simulate injuries, transfers

        // every month update player value, attributes, club ranking

        // simulates only the games that are not user played and that are not already simulated while user was playing
        $this->simulateGames();

        $this->instance->instance_date = $currentGameDate->addDay()->format('Y-m-d');

        $this->instance->save();
    }

    private function simulateGames()
    {
        $gamesByCompetition = [];
        $games = $this->competitionRepository->getScheduledGames($this->instance);

        $this->gameService->simulateRound($games);

        foreach ($games as $game) {
            if (!isset($gamesByCompetition[$game->competition_id])) {
                $gamesByCompetition[$game->competition_id] = [];
            }

            $gamesByCompetition[$game->competition_id][] = $game->toArray();
        }

        // create Competition Match Updater class which will take all the matches and deal with the updates
        $this->competitionService->setInstanceId($this->instance->id);
        $this->competitionService->setSeason($this->season);
        $this->competitionService->competitionsRoundUpdate($gamesByCompetition);

        //update other stuff like club ranking, player injuries, news service etc.
    }

    public function setSeason(Season $season)
    {
        $this->season = $season;
    }
}
