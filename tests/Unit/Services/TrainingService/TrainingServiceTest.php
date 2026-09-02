<?php

namespace Tests\Unit\Services\TrainingService;

use App\Models\Instance;
use App\Repositories\TrainingRepository;
use App\Services\TrainingService\Data\ScheduledGameData;
use App\Services\TrainingService\PlayerProgressCalculator;
use App\Services\TrainingService\TrainingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrainingServiceTest extends TestCase
{
    #[Test]
    public function it_selects_training_and_recovery_clubs_for_the_day(): void
    {
        $instance = (new Instance)->forceFill([
            'id' => 1,
            'instance_date' => '2027-06-10',
        ]);
        $repository = $this->createMock(TrainingRepository::class);
        $calculator = $this->createMock(PlayerProgressCalculator::class);
        $repository->expects($this->once())
            ->method('scheduledGames')
            ->willReturn(new Collection([
                new ScheduledGameData(1, 2, CarbonImmutable::parse('2027-06-10 15:00')),
                new ScheduledGameData(2, 3, CarbonImmutable::parse('2027-06-09 15:00')),
            ]));
        $repository->expects($this->once())
            ->method('clubsByIds')
            ->with(1, $this->callback(fn (Collection $ids): bool => $ids->all() === [3]))
            ->willReturn(new Collection);
        $repository->expects($this->once())
            ->method('clubsExceptIds')
            ->with(1, $this->callback(fn (Collection $ids): bool => $ids->sort()->values()->all() === [1, 2, 3]))
            ->willReturn(new Collection);

        (new TrainingService($repository, $calculator))->processDay($instance);
    }
}
