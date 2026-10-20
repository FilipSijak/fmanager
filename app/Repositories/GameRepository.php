<?php

namespace App\Repositories;

use App\Repositories\Interfaces\IGameRepository;
use App\Support\GameContext;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class GameRepository implements IGameRepository
{
    public function __construct(private readonly GameContext $gameContext) {}

    public function getFullGameData(int $gameId): ?array
    {
        $instanceId = $this->gameContext->instanceId();
        $game = DB::table('games AS g')
            ->select(
                'g.match_start',
                'g.winner',
                'g.home_team_goals',
                'g.away_team_goals',
                's.name AS stadium_name',
                'c1.name as home_team',
                'c2.name as away_team'
            )
            ->join('stadiums AS s', 'g.stadium_id', '=', 's.id')
            ->join('clubs AS c1', 'g.hometeam_id', '=', 'c1.id')
            ->join('clubs AS c2', 'g.awayteam_id', '=', 'c2.id')
            ->where('g.instance_id', $instanceId)
            ->where('g.season_id', $this->gameContext->seasonId())
            ->where('s.instance_id', $instanceId)
            ->where('c1.instance_id', $instanceId)
            ->where('c2.instance_id', $instanceId)
            ->where('g.id', $gameId)
            ->first();

        return $game === null ? null : (array) $game;
    }

    /** @return array<string, mixed>|null */
    public function details(int $gameId): ?array
    {
        $instanceId = $this->gameContext->instanceId();
        $game = DB::table('games AS g')
            ->join('clubs AS home', 'home.id', '=', 'g.hometeam_id')
            ->join('clubs AS away', 'away.id', '=', 'g.awayteam_id')
            ->join('competitions AS competition', 'competition.id', '=', 'g.competition_id')
            ->join('seasons AS season', 'season.id', '=', 'g.season_id')
            ->leftJoin('stadiums AS stadium', function (JoinClause $join): void {
                $join->on('stadium.id', '=', 'g.stadium_id')->on('stadium.instance_id', '=', 'g.instance_id');
            })
            ->where('g.id', $gameId)->where('g.instance_id', $instanceId)
            ->where('home.instance_id', $instanceId)->where('away.instance_id', $instanceId)
            ->where('competition.instance_id', $instanceId)->where('season.instance_id', $instanceId)
            ->first(['g.id', 'g.season_id', 'g.competition_id', 'g.match_start', 'g.status',
                'g.hometeam_id', 'g.awayteam_id', 'home.name AS home_team', 'away.name AS away_team',
                'g.home_team_goals', 'g.away_team_goals', 'g.attendance', 'g.leg_number', 'g.match_summary',
                'competition.name AS competition_name', 'stadium.name AS stadium_name',
                'season.start_date AS season_start', 'season.end_date AS season_end']);

        return $game === null ? null : (array) $game;
    }
}
