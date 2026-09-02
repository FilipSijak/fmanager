<?php

namespace App\Services\TrainingService;

readonly class PlayerProgressUpdate
{
    public function __construct(
        public array $progress,
        public array $player,
        public bool $countsAsTrained,
    ) {}
}
