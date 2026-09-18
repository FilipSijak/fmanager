<?php

namespace App\Listeners;

use App\Events\SeasonCompleted;
use App\Services\FinanceService\FinanceService;

class PayLeagueCompetitionPrizes
{
    public function __construct(
        private readonly FinanceService $financeService,
    ) {}

    public function handle(SeasonCompleted $event): void
    {
        $this->financeService->payLeagueCompetitionPrizes($event->instance);
    }
}
