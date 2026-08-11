<?php

namespace App\Listeners;

use App\Events\SeasonStarted;
use App\Services\SeasonService\SeasonService;

class StartSeason
{
    public function __construct(
        private readonly SeasonService $seasonService
    ) {}

    public function handle(SeasonStarted $event): void
    {
        $this->seasonService->start($event->instance);
    }
}
