<?php

namespace App\Services\InstanceService;

use App\Events\MatchDayCompleted;
use App\Events\MonthlyUpdate;
use App\Events\NextDay;
use App\Events\SeasonCompleted;
use App\Events\SeasonStarted;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Season;
use App\Models\User;
use App\Repositories\Competition\CompetitionScheduleRepository;
use App\Services\GameService\CompleteGameService;
use App\Services\GameService\MatchSimulationEngine;
use App\Services\PersonService\PersonService;
use App\Support\GameContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InstanceService implements IInstanceService
{
    private CompetitionScheduleRepository $competitionRepository;

    private Season $season;

    private MatchSimulationEngine $matchSimulationEngine;

    private CompleteGameService $completeGameService;

    private CreateInstance $createInstance;

    public function __construct(
        CompetitionScheduleRepository $competitionRepository,
        CreateInstance $createInstance,
        MatchSimulationEngine $matchSimulationEngine,
        CompleteGameService $completeGameService,
        private readonly GameContext $gameContext

    ) {
        $this->competitionRepository = $competitionRepository;
        $this->matchSimulationEngine = $matchSimulationEngine;
        $this->completeGameService = $completeGameService;
        $this->createInstance = $createInstance;
    }

    private function getInstance(): Instance
    {
        return Instance::findOrFail($this->gameContext->instanceId());
    }

    public function createNewInstance(User $user): Instance
    {
        return DB::transaction(fn (): Instance => $this->createInstance->instanceInit($user));
    }

    public function takeOverClub(User $user, int $instanceId, int $clubId, PersonService $personService): Instance
    {
        return DB::transaction(function () use ($user, $instanceId, $clubId, $personService): Instance {
            $instance = $user->instances()->lockForUpdate()->findOrFail($instanceId);
            abort_if($instance->club_id !== null || $instance->manager_id !== null, 409, 'A club has already been selected for this game.');
            $club = Club::query()->forInstance($instance->id)->findOrFail($clubId);
            $manager = $personService->assignUserManager($club, $user);
            $instance->club_id = $club->id;
            $instance->manager_id = $manager->id;
            $instance->save();

            return $instance;
        });
    }

    public function nextDay(): Instance
    {
        $instance = $this->getInstance();
        $this->season = Season::findOrFail($instance->season_id);
        $currentGameDate = Carbon::parse($instance->instance_date);
        $isMonthlyUpdate = $currentGameDate->day === 1;

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

        if ($isMonthlyUpdate) {
            event(new MonthlyUpdate($instance));
        }

        return $instance;
    }

    private function simulateGames()
    {
        $instance = $this->getInstance();
        $games = $this->competitionRepository->scheduledFor($instance);

        $completedGames = collect();

        foreach ($games as $game) {
            $result = $this->matchSimulationEngine->simulate($game);
            $completedGames->push($this->completeGameService->complete(
                $game->id,
                $result->homeGoals,
                $result->awayGoals,
                $result->summary
            ));
        }

        if ($completedGames->isNotEmpty()) {
            event(new MatchDayCompleted($instance, $completedGames));
        }
    }

    public function setSeason(Season $season)
    {
        $this->season = $season;
    }
}
