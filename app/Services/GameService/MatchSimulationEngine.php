<?php

namespace App\Services\GameService;

use App\Models\Game;

class MatchSimulationEngine
{
    private const GOAL_WEIGHTS = [0, 0, 0, 1, 1, 1, 2, 2, 3, 4];

    public function simulate(Game $game): MatchSimulationResult
    {
        $homeGoals = self::GOAL_WEIGHTS[array_rand(self::GOAL_WEIGHTS)];
        $awayGoals = self::GOAL_WEIGHTS[array_rand(self::GOAL_WEIGHTS)];
        $events = [];

        foreach (['home' => $homeGoals, 'away' => $awayGoals] as $team => $goals) {
            for ($goal = 0; $goal < $goals; $goal++) {
                $events[] = [
                    'minute' => random_int(1, 90),
                    'type' => 'goal',
                    'team' => $team,
                    'club_id' => $team === 'home' ? $game->hometeam_id : $game->awayteam_id,
                ];
            }
        }

        usort($events, fn (array $first, array $second) => $first['minute'] <=> $second['minute']);

        return new MatchSimulationResult($homeGoals, $awayGoals, [
            'engine_version' => 1,
            'events' => $events,
        ]);
    }
}
