<?php

namespace App\Services\InstanceService;

use App\Events\MatchDayCompleted;
use App\Events\MonthlyUpdate;
use App\Events\NextDay;
use App\Events\SeasonCompleted;
use App\Events\SeasonStarted;
use App\Models\Club;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Manager;
use App\Models\Season;
use App\Models\StaffCoaching;
use App\Models\User;
use App\Repositories\Competition\CompetitionScheduleRepository;
use App\Services\ClubService\ClubService;
use App\Services\GameService\CompleteGameService;
use App\Services\MatchEngineService\MatchEngineService;
use App\Services\PersonService\PersonService;
use App\Support\GameContext;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InstanceService implements IInstanceService
{
    private CompetitionScheduleRepository $competitionRepository;

    private Season $season;

    private MatchEngineService $matchEngineService;

    private CompleteGameService $completeGameService;

    private CreateInstance $createInstance;

    public function __construct(
        CompetitionScheduleRepository $competitionRepository,
        CreateInstance $createInstance,
        MatchEngineService $matchEngineService,
        CompleteGameService $completeGameService,
        private readonly GameContext $gameContext,
        private readonly ClubService $clubService,

    ) {
        $this->competitionRepository = $competitionRepository;
        $this->matchEngineService = $matchEngineService;
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

    public function takeOverClub(User $user, int $instanceId, int $clubId, int $leagueId, PersonService $personService): Instance
    {
        return DB::transaction(function () use ($user, $instanceId, $clubId, $leagueId, $personService): Instance {
            $instance = $user->instances()->lockForUpdate()->findOrFail($instanceId);
            abort_if($instance->club_id !== null || $instance->manager_id !== null, 409, 'A club has already been selected for this game.');
            $club = Club::query()->forInstance($instance->id)->findOrFail($clubId);
            $manager = $personService->assignUserManager($club, $user);
            $instance->club_id = $club->id;
            $instance->manager_id = $manager->id;
            $instance->followed_competition_id = $leagueId;
            $instance->save();

            $userManager = new Manager;
            $userManager->user_id = $user->id;
            $userManager->instance_id = $instance->id;
            $userManager->staff_coaching_id = $manager->id;
            $userManager->save();

            return $instance;
        });
    }

    /**
     * The user leaves their club, which becomes vacant, and keeps playing as an unemployed manager following the club's league.
     */
    public function resignFromClub(User $user, int $instanceId, PersonService $personService): Instance
    {
        return DB::transaction(function () use ($user, $instanceId, $personService): Instance {
            $instance = $user->instances()->lockForUpdate()->findOrFail($instanceId);
            abort_if($instance->club_id === null, 409, 'You are not managing a club in this game.');
            $personService->releaseUserManager(StaffCoaching::query()->findOrFail($instance->manager_id));
            $instance->club_id = null;
            $instance->save();

            return $instance;
        });
    }

    public function nextDay(): Instance
    {
        $instance = $this->getInstance();
        $this->ensureUserMatchIsNotInProgress($instance);
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

        $this->selectAiLineups($instance, $games);

        $completedGames = $this->gamesCompletedToday($instance);

        foreach ($games as $game) {
            $result = $this->matchEngineService->simulate($game);
            $completedGames->push($this->completeGameService->complete(
                $game->id,
                $result->homeGoals,
                $result->awayGoals,
                $result->summary,
                $result->playerStats,
            ));
        }

        if ($completedGames->isNotEmpty()) {
            event(new MatchDayCompleted($instance, $completedGames));
        }
    }

    /**
     * The user's match is played live before the day moves on, so it cannot be left half played.
     */
    private function ensureUserMatchIsNotInProgress(Instance $instance): void
    {
        if ($instance->club_id === null) {
            return;
        }

        $inProgress = Game::query()
            ->where('instance_id', $instance->id)
            ->forClub($instance->club_id)
            ->where('status', Game::STATUS_IN_PROGRESS)
            ->exists();

        if ($inProgress) {
            throw new DomainException('Finish your match before continuing.');
        }
    }

    /**
     * Today's games already completed before the rest are simulated: the user's match, played live.
     *
     * @return Collection<int, Game>
     */
    private function gamesCompletedToday(Instance $instance): Collection
    {
        return Game::query()
            ->where('instance_id', $instance->id)
            ->whereDate('match_start', $instance->instance_date)
            ->whereNotNull('processed_at')
            ->where('status', Game::STATUS_COMPLETED)
            ->get()
            ->toBase();
    }

    /**
     * Clubs the user does not manage pick their matchday squads before kick-off.
     *
     * @param  Collection<int, Game>  $games
     */
    private function selectAiLineups(Instance $instance, Collection $games): void
    {
        $aiClubIds = $games
            ->flatMap(fn (Game $game): array => [$game->hometeam_id, $game->awayteam_id])
            ->unique()
            ->reject(fn (int $clubId): bool => $clubId === (int) $instance->club_id);

        Club::query()->whereIn('id', $aiClubIds)->get()
            ->each(fn (Club $club) => $this->clubService->selectAiLineup($club, $instance->instance_date));
    }

    public function setSeason(Season $season)
    {
        $this->season = $season;
    }
}
