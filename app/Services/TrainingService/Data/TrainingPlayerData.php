<?php

namespace App\Services\TrainingService\Data;

readonly class TrainingPlayerData
{
    public function __construct(
        public int $id,
        public int $potential,
        public int $maxPotential,
        public int $physical,
        public string $position,
        public bool $injured,
        public int $condition,
        public array $attributes,
        public array $progress,
    ) {}

    public function attribute(string $field): int
    {
        return $this->attributes[$field];
    }

    public function accumulatedProgress(string $field): int
    {
        return $this->progress[$field];
    }
}
