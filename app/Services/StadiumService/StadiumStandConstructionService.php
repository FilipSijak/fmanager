<?php

namespace App\Services\StadiumService;

use App\Models\Instance;
use App\Models\StadiumStand;
use App\Models\StadiumStandConstruction;
use App\Repositories\StadiumRepository;
use App\Services\StadiumService\Domain\StadiumStandValidator;
use App\StadiumStandStatus;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class StadiumStandConstructionService
{
    public function __construct(
        private readonly StadiumService $stadiumService,
        private readonly StadiumRepository $stadiumRepository,
        private readonly StadiumStandValidator $stadiumStandValidator,
    ) {}

    public function durationInWeeks(int $capacityIncrease): int
    {
        if ($capacityIncrease <= 0 || $capacityIncrease % 1000 !== 0) {
            throw new DomainException('Stadium construction capacity must be a positive multiple of 1,000 seats.');
        }

        return intdiv($capacityIncrease, 1000);
    }

    public function startConstruction(StadiumStand $stadiumStand, int $targetCapacity, CarbonImmutable $startedAt): StadiumStandConstruction
    {
        return DB::transaction(function () use ($stadiumStand, $targetCapacity, $startedAt): StadiumStandConstruction {
            $lockedStand = StadiumStand::query()->whereKey($stadiumStand->id)->lockForUpdate()->firstOrFail();
            $stadium = $lockedStand->stadium()->firstOrFail();

            if (StadiumStandConstruction::query()
                ->where('stadium_stand_id', $lockedStand->id)
                ->whereNull('completed_at')
                ->exists()) {
                throw new DomainException('This stadium stand already has construction in progress.');
            }

            $currentCapacity = (int) $lockedStand->capacity;
            $capacityIncrease = $targetCapacity - $currentCapacity;

            if ($capacityIncrease <= 0) {
                throw new DomainException('The new stadium stand capacity must be greater than its current capacity.');
            }

            $this->stadiumStandValidator->validatePosition($stadium, $lockedStand->position);
            $maximumCapacity = $this->stadiumRepository->maximumCapacityForStand($stadium->type, $lockedStand->position);
            $this->stadiumStandValidator->validateCapacityValue($targetCapacity, $maximumCapacity);

            $durationInWeeks = $this->durationInWeeks($capacityIncrease);
            $lockedStand->forceFill([
                'capacity' => $targetCapacity,
                'status' => StadiumStandStatus::UNDER_CONSTRUCTION,
            ])->save();

            $construction = StadiumStandConstruction::query()->create([
                'instance_id' => $stadium->instance_id,
                'stadium_id' => $stadium->id,
                'stadium_stand_id' => $lockedStand->id,
                'target_capacity' => $targetCapacity,
                'capacity_increase' => $capacityIncrease,
                'started_at' => $startedAt,
                'completes_at' => $startedAt->addWeeks($durationInWeeks),
            ]);

            $this->stadiumService->recalculateCapacities($stadium->fresh());

            return $construction;
        });
    }

    public function completeForInstance(Instance $instance, CarbonImmutable $asOf): int
    {
        $completed = 0;
        $affectedStadiumIds = [];

        StadiumStandConstruction::query()
            ->where('instance_id', $instance->id)
            ->whereNull('completed_at')
            ->whereDate('completes_at', '<=', $asOf->toDateString())
            ->get()
            ->each(function (StadiumStandConstruction $construction) use (&$completed, &$affectedStadiumIds, $asOf): void {
                DB::transaction(function () use ($construction, &$completed, &$affectedStadiumIds, $asOf): void {
                    $lockedConstruction = StadiumStandConstruction::query()
                        ->whereKey($construction->id)
                        ->lockForUpdate()
                        ->first();

                    if ($lockedConstruction === null) {
                        return;
                    }

                    $affectedStadiumIds[$lockedConstruction->stadium_id] = true;
                    $stand = StadiumStand::query()->whereKey($lockedConstruction->stadium_stand_id)->first();
                    if ($stand !== null) {
                        $stand->forceFill(['status' => StadiumStandStatus::ACTIVE])->saveQuietly();
                    }

                    $lockedConstruction->update(['completed_at' => $asOf->toDateString()]);
                    $completed++;
                });
            });

        foreach (array_keys($affectedStadiumIds) as $stadiumId) {
            $stadium = $this->stadiumRepository->stadiumById((int) $stadiumId);
            if ($stadium !== null) {
                $this->stadiumService->recalculateCapacities($stadium);
            }
        }

        return $completed;
    }
}
