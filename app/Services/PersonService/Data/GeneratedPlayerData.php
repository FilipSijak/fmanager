<?php

namespace App\Services\PersonService\Data;

readonly class GeneratedPlayerData
{
    /**
     * @param  list<string>  $positions
     * @param  array<string, int>  $attributes
     */
    public function __construct(
        public PersonInfo $personDetails,
        public string $position,
        public PotentialByCategoryData $potentialByCategory,
        public int $maxPotential,
        public float $potential,
        public array $positions,
        public array $attributes,
    ) {}
}
