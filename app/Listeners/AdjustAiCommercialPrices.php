<?php

namespace App\Listeners;

use App\Events\MonthlyUpdate;
use App\Services\CommercialService\AiCommercialPricingService;

class AdjustAiCommercialPrices
{
    public function __construct(private readonly AiCommercialPricingService $pricing) {}

    /**
     * Handle the event.
     */
    public function handle(MonthlyUpdate $event): void
    {
        $this->pricing->adjustForMonth($event->instance);
    }
}
