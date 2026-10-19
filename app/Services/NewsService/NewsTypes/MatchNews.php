<?php

namespace App\Services\NewsService\NewsTypes;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Services\NewsService\NewsItem;
use App\Services\NewsService\NewsPriority;
use App\Services\NewsService\NewsType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MatchNews
{
    /**
     * News for the user's own match and a round-up of their league's results on a match day.
     *
     * @param  Collection<int, Game>  $games
     * @return list<NewsItem>
     */
    public function forMatchDay(Instance $instance, Collection $games): array
    {
        if ($instance->club_id === null || $games->isEmpty()) {
            return [];
        }

        $clubNames = Club::query()
            ->whereKey($games->pluck('hometeam_id')->merge($games->pluck('awayteam_id'))->unique())
            ->pluck('name', 'id');
        $competitionNames = Competition::query()->whereKey($games->pluck('competition_id')->unique())->pluck('name', 'id');
        $items = $games
            ->filter(fn (Game $game): bool => in_array($instance->club_id, [$game->hometeam_id, $game->awayteam_id], true))
            ->map(fn (Game $game): NewsItem => $this->clubResult($instance, $game, $clubNames, $competitionNames))
            ->values()
            ->all();

        $leagueId = $this->leagueIdForClub($instance);
        $leagueGames = $games->where('competition_id', $leagueId)->values();

        if ($leagueId !== null && $leagueGames->isNotEmpty()) {
            $items[] = $this->leagueRoundUp($instance, $leagueId, $leagueGames, $clubNames, $competitionNames);
        }

        return $items;
    }

    /**
     * @param  Collection<int, string>  $clubNames
     * @param  Collection<int, string>  $competitionNames
     */
    private function clubResult(Instance $instance, Game $game, Collection $clubNames, Collection $competitionNames): NewsItem
    {
        $isHome = $game->hometeam_id === $instance->club_id;
        $club = $clubNames[$instance->club_id];
        $opponent = $clubNames[$isHome ? $game->awayteam_id : $game->hometeam_id];
        $clubGoals = $isHome ? $game->home_team_goals : $game->away_team_goals;
        $opponentGoals = $isHome ? $game->away_team_goals : $game->home_team_goals;
        $result = match (true) {
            $clubGoals > $opponentGoals => "{$club} beat {$opponent} {$clubGoals}-{$opponentGoals}",
            $clubGoals < $opponentGoals => "{$club} lost {$clubGoals}-{$opponentGoals} to {$opponent}",
            default => "{$club} drew {$clubGoals}-{$opponentGoals} with {$opponent}",
        };

        return $this->item(
            $instance,
            $instance->club_id,
            $game->competition_id,
            $this->scoreLine($game, $clubNames),
            "{$result} in the {$competitionNames[$game->competition_id]}.",
            NewsPriority::High,
        );
    }

    /**
     * @param  Collection<int, Game>  $games
     * @param  Collection<int, string>  $clubNames
     * @param  Collection<int, string>  $competitionNames
     */
    private function leagueRoundUp(Instance $instance, int $leagueId, Collection $games, Collection $clubNames, Collection $competitionNames): NewsItem
    {
        return $this->item(
            $instance,
            null,
            $leagueId,
            "{$competitionNames[$leagueId]} round-up",
            $games->map(fn (Game $game): string => $this->scoreLine($game, $clubNames))->implode("\n"),
            NewsPriority::Normal,
        );
    }

    /**
     * @param  Collection<int, string>  $clubNames
     */
    private function scoreLine(Game $game, Collection $clubNames): string
    {
        return "{$clubNames[$game->hometeam_id]} {$game->home_team_goals}-{$game->away_team_goals} {$clubNames[$game->awayteam_id]}";
    }

    private function leagueIdForClub(Instance $instance): ?int
    {
        $leagueId = DB::table('competition_season')
            ->join('competitions', 'competitions.id', '=', 'competition_season.competition_id')
            ->where('competition_season.instance_id', $instance->id)
            ->where('competition_season.season_id', $instance->season_id)
            ->where('competition_season.club_id', $instance->club_id)
            ->where('competitions.type', 'league')
            ->value('competition_season.competition_id');

        return $leagueId === null ? null : (int) $leagueId;
    }

    private function item(Instance $instance, ?int $clubId, int $competitionId, string $title, string $content, NewsPriority $priority): NewsItem
    {
        return new NewsItem(
            instanceId: $instance->id,
            seasonId: $instance->season_id,
            clubId: $clubId,
            competitionId: $competitionId,
            title: $title,
            content: $content,
            type: NewsType::Match,
            priority: $priority,
            publishedAt: Carbon::parse($instance->instance_date),
        );
    }
}
