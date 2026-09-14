<?php

namespace App\Listeners;

use App\Events\NextDay;
use App\Services\StadiumService\StadiumService;
use App\Services\StadiumService\StadiumStandConstructionService;
use Carbon\CarbonImmutable;

class CompleteStadiumStandConstruction
{
    public function __construct(private readonly StadiumStandConstructionService $constructionService,
        private readonly StadiumService $stadiumService,
    ) {}

    public function handle(NextDay $event): void
    {
        $this->constructionService->completeForInstance(
            $event->instance,
            CarbonImmutable::parse($event->instance->instance_date)->startOfDay(),
        );
        $this->stadiumService->recalculateCapacitiesForInstance($event->instance);
    }
}
