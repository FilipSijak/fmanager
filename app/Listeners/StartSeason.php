<?php

namespace App\Listeners;

use App\Events\SeasonStarted;
use App\Services\SeasonService\SeasonStartService;

class StartSeason
{
    public function __construct(
        private readonly SeasonStartService $seasonStartService
    ) {}

    public function handle(SeasonStarted $event): void
    {
        $this->seasonStartService->start($event->instance);
    }
}
