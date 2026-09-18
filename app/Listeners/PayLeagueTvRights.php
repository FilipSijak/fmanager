<?php

namespace App\Listeners;

use App\Events\SeasonStarted;
use App\Services\FinanceService\FinanceService;

class PayLeagueTvRights
{
    public function __construct(
        private readonly FinanceService $financeService,
    ) {}

    public function handle(SeasonStarted $event): void
    {
        $this->financeService->payLeagueTvRights($event->instance);
    }
}
