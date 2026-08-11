<?php

namespace App\Listeners;

use App\Events\SeasonCompleted;
use App\Services\SeasonService\SeasonService;

class CompleteSeason
{
    public function __construct(
        private readonly SeasonService $seasonService
    ) {}

    public function handle(SeasonCompleted $event): void
    {
        $this->seasonService->complete($event->instance);
    }
}
