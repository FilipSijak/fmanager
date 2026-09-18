<?php

namespace App\Listeners;

use App\Events\PostMatch;
use App\Services\FinanceService\FinanceService;

class PayContinentalMatchPrizes
{
    public function __construct(
        private readonly FinanceService $financeService,
    ) {}

    public function handle(PostMatch $event): void
    {
        $this->financeService->payContinentalMatchPrizes($event->game);
    }
}
