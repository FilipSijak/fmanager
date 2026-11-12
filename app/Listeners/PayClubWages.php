<?php

namespace App\Listeners;

use App\Events\NextDay;
use App\Services\FinanceService\PayrollService;

class PayClubWages
{
    public function __construct(private readonly PayrollService $payroll) {}

    /**
     * Handle the event.
     */
    public function handle(NextDay $event): void
    {
        $this->payroll->payDueWages($event->instance);
    }
}
