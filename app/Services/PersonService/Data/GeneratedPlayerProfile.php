<?php

namespace App\Services\PersonService\Data;

readonly class GeneratedPlayerProfile
{
    public function __construct(
        public int $potential,
        public string $position,
        public PotentialByCategoryData $potentialByCategory,
    ) {}
}
