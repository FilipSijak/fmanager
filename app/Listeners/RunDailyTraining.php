<?php

namespace App\Listeners;

use App\Events\NextDay;
use App\Services\TrainingService\TrainingService;

class RunDailyTraining
{
    public function __construct(private readonly TrainingService $trainingService) {}

    public function handle(NextDay $event): void
    {
        $this->trainingService->processDay($event->instance);
    }
}
