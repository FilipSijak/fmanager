<?php

namespace App\Services\GameService;

final class MatchSimulationResult
{
    public function __construct(
        public readonly int $homeGoals,
        public readonly int $awayGoals,
        public readonly array $summary
    ) {}
}
