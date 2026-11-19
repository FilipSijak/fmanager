<?php

namespace App\Listeners;

use App\Events\NextDay;
use App\Services\TransferService\TransferFinancialSettlement;

class ProcessTransfers
{
    public function __construct(private readonly TransferFinancialSettlement $settlement) {}

    public function handle(NextDay $event): void
    {
        $this->settlement->processDueInstallments($event->instance);
    }
}
