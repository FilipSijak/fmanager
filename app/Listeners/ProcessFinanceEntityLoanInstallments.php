<?php

namespace App\Listeners;

use App\Events\NextDay;
use App\Services\FinanceService\FinanceService;
use Carbon\CarbonImmutable;

class ProcessFinanceEntityLoanInstallments
{
    public function __construct(
        private readonly FinanceService $financeService,
    ) {}

    public function handle(NextDay $event): void
    {
        $this->financeService->processDueLoanInstallments(
            $event->instance,
            CarbonImmutable::parse($event->instance->instance_date)->startOfDay(),
        );
    }
}
