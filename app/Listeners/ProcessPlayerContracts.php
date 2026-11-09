<?php

namespace App\Listeners;

use App\Events\NextDay;
use App\Services\PersonService\PersonService;

class ProcessPlayerContracts
{
    public function __construct(private readonly PersonService $persons) {}

    public function handle(NextDay $event): void
    {
        $this->persons->processPlayerContracts($event->instance);
    }
}
