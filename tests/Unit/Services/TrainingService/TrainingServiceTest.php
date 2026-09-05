<?php

namespace Tests\Unit\Services\TrainingService;

use App\Models\Club;
use App\Models\Instance;
use App\Repositories\TrainingRepository;
use App\Services\TrainingService\Data\ScheduledGameData;
use App\Services\TrainingService\Data\TrainingPlayerData;
use App\Services\TrainingService\PlayerProgressCalculator;
use App\Services\TrainingService\PlayerProgressUpdate;
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
        $club = (new Club)->forceFill(['id' => 3, 'instance_id' => 1]);
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
            ->willReturn(new Collection([$club]));
        $repository->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(fn (\Closure $callback) => $callback());
        $repository->expects($this->once())
            ->method('recoverClubsCondition')
            ->with(
                1,
                $this->callback(fn (Collection $ids): bool => $ids->all() === [3]),
                CarbonImmutable::parse('2027-06-10'),
                10
            );
        $repository->expects($this->once())
            ->method('clubsExceptIds')
            ->with(1, $this->callback(fn (Collection $ids): bool => $ids->sort()->values()->all() === [1, 2, 3]))
            ->willReturn(new Collection);

        (new TrainingService($repository, $calculator))->processDay($instance);
    }

    #[Test]
    public function it_calculates_and_bulk_updates_a_club_training_session(): void
    {
        $instance = (new Instance)->forceFill([
            'id' => 1,
            'instance_date' => '2027-06-10',
        ]);
        $club = (new Club)->forceFill(['id' => 10, 'instance_id' => 1]);
        $player = new TrainingPlayerData(
            7, 100, 150, 150, 150, 150, 'CB', false, 90, [], []
        );
        $repository = $this->createMock(TrainingRepository::class);
        $calculator = $this->createMock(PlayerProgressCalculator::class);

        $repository->method('scheduledGames')->willReturn(new Collection);
        $repository->method('clubsByIds')->willReturn(new Collection);
        $repository->method('clubsExceptIds')->willReturn(new Collection([$club]));
        $repository->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(fn (\Closure $callback) => $callback());
        $repository->expects($this->once())
            ->method('playerIdsForTraining')
            ->with(1, $this->callback(fn (Collection $ids): bool => $ids->all() === [10]))
            ->willReturn(new Collection([7]));
        $repository->expects($this->once())
            ->method('playersForTraining')
            ->willReturn(new Collection([$player]));
        $repository->expects($this->once())
            ->method('schedulesForPlayers')
            ->with($this->callback(fn (Collection $ids): bool => $ids->all() === [7]))
            ->willReturn(new Collection([7 => []]));
        $calculator->expects($this->once())
            ->method('forTrainingSession')
            ->willReturn(new PlayerProgressUpdate(
                ['condition' => 93],
                ['pace' => 11],
            ));
        $repository->expects($this->once())
            ->method('bulkUpdateProgress')
            ->with([7 => ['condition' => 93]]);
        $repository->expects($this->once())
            ->method('bulkUpdatePlayers')
            ->with([7 => ['pace' => 11]]);

        (new TrainingService($repository, $calculator))->processDay($instance);
    }

    #[Test]
    public function it_processes_players_in_batches_of_fifty(): void
    {
        $instance = (new Instance)->forceFill(['id' => 1, 'instance_date' => '2027-06-10']);
        $club = (new Club)->forceFill(['id' => 10, 'instance_id' => 1]);
        $playerIds = new Collection(range(1, 51));
        $batchSizes = [];
        $repository = $this->createMock(TrainingRepository::class);
        $calculator = $this->createMock(PlayerProgressCalculator::class);

        $repository->method('scheduledGames')->willReturn(new Collection);
        $repository->method('clubsByIds')->willReturn(new Collection);
        $repository->method('clubsExceptIds')->willReturn(new Collection([$club]));
        $repository->method('transaction')->willReturnCallback(fn (\Closure $callback) => $callback());
        $repository->method('playerIdsForTraining')->willReturn($playerIds);
        $repository->expects($this->exactly(2))
            ->method('playersForTraining')
            ->willReturnCallback(function (int $instanceId, Collection $ids) use (&$batchSizes): Collection {
                $batchSizes[] = $ids->count();

                return $ids->map(fn (int $id): TrainingPlayerData => new TrainingPlayerData(
                    $id, 100, 150, 150, 150, 150, 'CB', false, 90, [], []
                ));
            });
        $repository->expects($this->exactly(2))
            ->method('schedulesForPlayers')
            ->willReturn(new Collection);
        $repository->expects($this->exactly(2))->method('bulkUpdateProgress');
        $repository->expects($this->exactly(2))->method('bulkUpdatePlayers');
        $calculator->expects($this->exactly(51))
            ->method('forTrainingSession')
            ->willReturn(new PlayerProgressUpdate(['condition' => 93], []));

        (new TrainingService($repository, $calculator))->processDay($instance);

        $this->assertSame([50, 1], $batchSizes);
    }
}
