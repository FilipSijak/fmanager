<?php

namespace App\Services\TrainingService\Data;

use Carbon\CarbonImmutable;

readonly class ScheduledGameData
{
    public function __construct(
        public int $homeClubId,
        public int $awayClubId,
        public CarbonImmutable $kickoff,
    ) {}

    public function clubIds(): array
    {
        return [$this->homeClubId, $this->awayClubId];
    }
}
