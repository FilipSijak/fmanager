<?php

namespace App\Listeners;

use App\Events\NextDay;
use App\Services\ManagerJobService\ManagerJobService;

class FillManagerVacancies
{
    public function __construct(private readonly ManagerJobService $managerJobService) {}

    public function handle(NextDay $event): void
    {
        $this->managerJobService->fillVacancies($event->instance);
    }
}
