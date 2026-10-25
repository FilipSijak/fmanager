<?php

namespace App\Services\MatchEngineService\Data;

/**
 * A starter's ratings for the actions the engine credits to individual players, on the 1-20 attribute scale.
 */
final class MatchPlayer
{
    public function __construct(
        public readonly int $playerId,
        /** Position played in this match (the lineup slot), not necessarily the player's natural position. */
        public readonly string $position,
        public readonly float $shooting,
        public readonly float $creating,
        public readonly float $passing,
        public readonly float $tackling,
        public readonly float $intercepting,
        public readonly float $dribbling,
        public readonly float $aggression,
        public readonly float $decisions,
        public readonly float $goalkeeping,
    ) {}
}
