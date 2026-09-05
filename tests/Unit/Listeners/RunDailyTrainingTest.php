<?php

namespace Tests\Unit\Listeners;

use App\Events\NextDay;
use App\Listeners\RunDailyTraining;
use App\Models\Instance;
use App\Services\TrainingService\TrainingService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RunDailyTrainingTest extends TestCase
{
    #[Test]
    public function it_delegates_the_day_to_the_training_service(): void
    {
        $instance = new Instance;
        $trainingService = $this->createMock(TrainingService::class);
        $trainingService->expects($this->once())
            ->method('processDay')
            ->with($instance);

        (new RunDailyTraining($trainingService))->handle(new NextDay($instance));
    }
}
