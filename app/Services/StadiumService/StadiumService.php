<?php

namespace App\Services\StadiumService;

use App\Models\Stadium;
use App\Models\StadiumStand;
use App\StadiumStandStatus;

class StadiumService
{
    public function typeForCapacity(int $capacity): StadiumType
    {
        return StadiumType::fromCapacity($capacity);
    }

    public function commercialLimitForType(StadiumType $type): int
    {
        return $type->commercialLimit();
    }

    public function recalculateCapacities(Stadium $stadium): void
    {
        $stands = StadiumStand::query()->whereBelongsTo($stadium);

        $stadium->forceFill([
            'capacity' => (int) (clone $stands)->sum('capacity'),
            'active_capacity' => (int) $stands
                ->where('status', StadiumStandStatus::ACTIVE->value)
                ->sum('capacity'),
        ])->saveQuietly();
    }
}
