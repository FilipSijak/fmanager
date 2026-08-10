<?php

namespace App\Listeners;

use App\Events\SeasonCompleted;
use App\Services\SeasonService\SeasonCompletionService;

class CompleteSeason
{
    public function __construct(
        private readonly SeasonCompletionService $seasonCompletionService
    ) {}

    public function handle(SeasonCompleted $event): void
    {
        $this->seasonCompletionService->complete($event->instance);
    }
}
