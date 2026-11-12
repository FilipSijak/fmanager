<?php

namespace App\Listeners;

use App\Events\WeeklyUpdate;
use App\Services\FinanceService\PayrollService;

class PayClubWages
{
    public function __construct(private readonly PayrollService $payroll) {}

    /**
     * Handle the event.
     */
    public function handle(WeeklyUpdate $event): void
    {
        $this->payroll->payDueWages($event->instance);
    }
}
