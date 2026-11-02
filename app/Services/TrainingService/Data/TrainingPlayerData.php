<?php

namespace App\Services\TrainingService\Data;

use App\Models\Player;

readonly class TrainingPlayerData
{
    public function __construct(
        public int $id,
        public int $potential,
        public int $maxPotential,
        public int $technical,
        public int $mental,
        public int $physical,
        public string $position,
        public bool $injured,
        public int $condition,
        public array $attributes,
        public array $progress,
        public ?int $currentTechnical = null,
        public ?int $currentMental = null,
        public ?int $currentPhysical = null,
    ) {}

    /** @param list<string> $fields */
    public static function fromPlayer(Player $player, array $fields): self
    {
        return new self(
            id: (int) $player->id,
            potential: (int) $player->potential,
            maxPotential: (int) $player->max_potential,
            technical: (int) $player->technical,
            mental: (int) $player->mental,
            physical: (int) $player->physical,
            position: $player->position,
            injured: (bool) $player->is_injured,
            condition: (int) $player->training_condition,
            attributes: collect($fields)->mapWithKeys(fn (string $field): array => [$field => (int) $player->{$field}])->all(),
            progress: collect($fields)->mapWithKeys(fn (string $field): array => [$field => (int) $player->{'progress_'.$field}])->all(),
            currentTechnical: (int) ($player->current_technical_potential ?: $player->technical),
            currentMental: (int) ($player->current_mental_potential ?: $player->mental),
            currentPhysical: (int) ($player->current_physical_potential ?: $player->physical),
        );
    }

    public function attribute(string $field): int
    {
        return $this->attributes[$field];
    }

    public function accumulatedProgress(string $field): int
    {
        return $this->progress[$field];
    }
}
