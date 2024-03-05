<?php

namespace App\Repositories;

use App\Repositories\Interfaces\IGameRepository;
use Illuminate\Support\Facades\DB;

class GameRepository extends CoreRepository implements IGameRepository
{
    public function getFullGameData(int $gameId): array
    {
        return (array) DB::table('games AS g')
            ->select(
                'g.match_start',
                'g.winner',
                'g.home_team_goals',
                'g.away_team_goals',
                's.name',
                'c1.name as home team',
                'c2.name as away_team'
            )
            ->join('stadiums AS s', 'g.stadium_id', '=', 's.id')
            ->join('clubs as c1', 'g.hometeam_id', '=', 'c1.id')
            ->join('clubs as c2', 'g.awayteam_id', '=', 'c2.id')
            ->where('season_id', $this->seasonId)
            ->where('c1.instance_id', $this->instanceId)
            ->where('g.id', $gameId)
            ->first();
    }
}
