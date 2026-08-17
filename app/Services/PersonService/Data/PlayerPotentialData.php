<?php

namespace App\Services\PersonService\Data;

readonly class PlayerPotentialData
{
    public function __construct(
        public int $potential,
        public string $position,
        public PotentialByCategoryData $potentialByCategory,
    ) {}
}
