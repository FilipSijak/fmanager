<?php

namespace App\Services\StadiumService\Domain;

use App\Models\StadiumStand;
use App\Models\StadiumStandConstruction;
use App\Repositories\StadiumRepository;
use App\StadiumStandStatus;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class StartStandConstruction
{
    public function __construct(
        private readonly StadiumRepository $stadiumRepository,
        private readonly StadiumStandValidator $stadiumStandValidator,
        private readonly StadiumCapacityManager $stadiumCapacityManager,
    ) {}

    public function durationInWeeks(int $capacityIncrease): int
    {
        if ($capacityIncrease <= 0 || $capacityIncrease % 1000 !== 0) {
            throw new DomainException('Stadium construction capacity must be a positive multiple of 1,000 seats.');
        }

        return intdiv($capacityIncrease, 1000);
    }

    public function handle(StadiumStand $stadiumStand, int $targetCapacity, CarbonImmutable $startedAt): StadiumStandConstruction
    {
        return DB::transaction(function () use ($stadiumStand, $targetCapacity, $startedAt): StadiumStandConstruction {
            $lockedStand = StadiumStand::query()->whereKey($stadiumStand->id)->lockForUpdate()->firstOrFail();
            $stadium = $lockedStand->stadium()->firstOrFail();

            if (StadiumStandConstruction::query()->where('stadium_stand_id', $lockedStand->id)->whereNull('completed_at')->exists()) {
                throw new DomainException('This stadium stand already has construction in progress.');
            }

            $capacityIncrease = $targetCapacity - (int) $lockedStand->capacity;
            if ($capacityIncrease <= 0) {
                throw new DomainException('The new stadium stand capacity must be greater than its current capacity.');
            }

            $this->stadiumStandValidator->validatePosition($stadium, $lockedStand->position);
            $maximumCapacity = $this->stadiumRepository->maximumCapacityForStand($stadium->type, $lockedStand->position);
            $this->stadiumStandValidator->validateCapacityValue($targetCapacity, $maximumCapacity);

            $lockedStand->forceFill(['capacity' => $targetCapacity, 'status' => StadiumStandStatus::UNDER_CONSTRUCTION])->save();
            $construction = StadiumStandConstruction::query()->create([
                'instance_id' => $stadium->instance_id,
                'stadium_id' => $stadium->id,
                'stadium_stand_id' => $lockedStand->id,
                'target_capacity' => $targetCapacity,
                'capacity_increase' => $capacityIncrease,
                'started_at' => $startedAt,
                'completes_at' => $startedAt->addWeeks($this->durationInWeeks($capacityIncrease)),
            ]);

            $this->stadiumCapacityManager->recalculate($stadium->fresh());

            return $construction;
        });
    }
}
