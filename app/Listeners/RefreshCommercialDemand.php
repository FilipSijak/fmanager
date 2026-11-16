<?php

namespace App\Listeners;

use App\Events\MonthlyUpdate;
use App\Services\CommercialService\CommercialDemandService;

class RefreshCommercialDemand
{
    public function __construct(private readonly CommercialDemandService $demand) {}

    /**
     * Handle the event.
     */
    public function handle(MonthlyUpdate $event): void
    {
        $this->demand->refreshForMonth($event->instance);
    }
}
