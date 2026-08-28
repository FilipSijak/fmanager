<?php

namespace App\Repositories;

use App\Repositories\Interfaces\IGameRepository;
use App\Support\GameContext;
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
}
