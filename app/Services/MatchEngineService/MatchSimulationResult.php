<?php

namespace App\Services\MatchEngineService;

final class MatchSimulationResult
{
    public function __construct(
        public readonly int $homeGoals,
        public readonly int $awayGoals,
        public readonly array $summary,
        /** @var list<array<string, int|float|bool|string>> One row per player who played, shaped like game_player_stats. */
        public readonly array $playerStats = [],
    ) {}
}
