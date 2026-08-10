<?php

namespace App\Services\GameService;

use App\Models\Competition;
use App\Models\Game;
use App\Models\Season;
use App\Services\CompetitionService\Competitions\CompetitionUpdater;
use App\Services\CompetitionService\Competitions\TournamentUpdater;
use App\Support\GameContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

class CompleteGameService
{
    public function __construct(
        private readonly CompetitionUpdater $competitionUpdater,
        private readonly TournamentUpdater $tournamentUpdater,
        private readonly GameContext $gameContext
    ) {}

    public function complete(int $gameId, int $homeGoals, int $awayGoals, ?array $matchSummary = null): Game
    {
        return DB::transaction(function () use ($gameId, $homeGoals, $awayGoals, $matchSummary): Game {
            $game = $this->findGame($gameId);

            if ($game->processed_at !== null) {
                return $game;
            }

            if (in_array($game->status, [Game::STATUS_CANCELLED, Game::STATUS_ABANDONED], true)) {
                throw new LogicException('A cancelled or abandoned game cannot be completed.');
            }

            $game->home_team_goals = $homeGoals;
            $game->away_team_goals = $awayGoals;
            $game->winner = $homeGoals > $awayGoals ? 1 : ($awayGoals > $homeGoals ? 2 : 3);
            $game->match_summary = $matchSummary === null ? null : json_encode($matchSummary, JSON_THROW_ON_ERROR);
            $game->status = Game::STATUS_COMPLETED;
            $game->save();

            $this->competitionUpdater->setGamesByCompetition([
                $game->competition_id => [$game->toArray()],
            ]);
            $this->competitionUpdater->distributeGamesForCompetitionUpdates(
                Season::findOrFail($game->season_id),
                $game->instance_id
            );

            $game->processed_at = now();
            $game->save();

            return $game->fresh();
        });
    }

    public function postpone(int $gameId, string $newMatchStart): Game
    {
        return DB::transaction(function () use ($gameId, $newMatchStart): Game {
            $game = $this->findGame($gameId);
            $this->ensureMutable($game);
            $game->match_start = Carbon::parse($newMatchStart);
            $game->status = Game::STATUS_POSTPONED;
            $game->save();

            return $game->fresh();
        });
    }

    public function cancel(int $gameId): Game
    {
        return DB::transaction(function () use ($gameId): Game {
            $game = $this->findGame($gameId);

            if ($game->processed_at !== null) {
                return $game;
            }

            $game->status = Game::STATUS_CANCELLED;
            $game->processed_at = now();
            $game->save();

            $competition = Competition::findOrFail($game->competition_id);
            $groupsActive = DB::table('competition_season')
                ->where('instance_id', $game->instance_id)
                ->where('season_id', $game->season_id)
                ->where('competition_id', $game->competition_id)
                ->where('groups_active', true)
                ->exists();

            if ($competition->type === 'tournament' && $groupsActive) {
                $this->tournamentUpdater->setInstanceId($game->instance_id);
                $this->tournamentUpdater->setSeason(Season::findOrFail($game->season_id));
                $this->tournamentUpdater->transitionToKnockoutIfFinished($game->toArray());
            }

            return $game->fresh();
        });
    }

    private function findGame(int $gameId): Game
    {
        $game = Game::query()->lockForUpdate()->findOrFail($gameId);

        if ($this->gameContext->hasInstanceId() && $game->instance_id !== $this->gameContext->instanceId()) {
            abort(404);
        }
        if ($this->gameContext->hasSeasonId() && $game->season_id !== $this->gameContext->seasonId()) {
            abort(404);
        }

        return $game;
    }

    private function ensureMutable(Game $game): void
    {
        if ($game->processed_at !== null || in_array($game->status, [Game::STATUS_COMPLETED, Game::STATUS_CANCELLED, Game::STATUS_ABANDONED], true)) {
            throw new LogicException('A terminal game cannot be changed.');
        }
    }
}
