<?php

namespace App\Listeners;

use App\Events\NextDay;
use App\Services\ContractService\ContractService;

class ProcessPlayerContracts
{
    public function __construct(private readonly ContractService $contracts) {}

    public function handle(NextDay $event): void
    {
        $this->contracts->processPlayerContracts($event->instance);
    }
}
