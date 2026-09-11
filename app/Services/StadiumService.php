<?php

namespace App\Services;

use App\Models\Stadium;
use App\Models\StadiumStand;
use App\StadiumStandStatus;

class StadiumService
{
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
