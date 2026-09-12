<?php

namespace App\Observers;

use App\Models\StadiumStand;
use App\Services\StadiumService;

class StadiumStandObserver
{
    public function __construct(private readonly StadiumService $stadiumService) {}

    public function saved(StadiumStand $stadiumStand): void
    {
        $this->recalculate($stadiumStand);
    }

    public function deleted(StadiumStand $stadiumStand): void
    {
        $this->recalculate($stadiumStand);
    }

    private function recalculate(StadiumStand $stadiumStand): void
    {
        $this->stadiumService->recalculateCapacities($stadiumStand->stadium()->firstOrFail());
    }
}
