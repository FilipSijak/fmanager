<?php

namespace App\Services\StadiumService;

use App\Models\BaseData\BaseStadiumStandCapacityLimit;
use App\Models\Instance;
use App\Models\StadiumStand;
use App\Models\StadiumStandConstruction;
use App\StadiumStandStatus;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class StadiumStandConstructionService
{
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

            if (StadiumStandConstruction::query()->where('stadium_stand_id', $lockedStand->id)->exists()) {
                throw new DomainException('This stadium stand already has construction in progress.');
            }

            $currentCapacity = (int) $lockedStand->capacity;
            $capacityIncrease = $targetCapacity - $currentCapacity;

            if ($capacityIncrease <= 0) {
                throw new DomainException('The new stadium stand capacity must be greater than its current capacity.');
            }

            $maximumCapacity = (int) BaseStadiumStandCapacityLimit::query()
                ->where('stadium_type', $stadium->type->value)
                ->where('position', $lockedStand->position->value)
                ->value('maximum_capacity');

            if ($maximumCapacity === 0) {
                throw new DomainException('This stand position is not available for the stadium type.');
            }

            if ($targetCapacity > $maximumCapacity) {
                throw new DomainException('Stadium stand capacity exceeds the maximum for its position.');
            }

            $durationInWeeks = $this->durationInWeeks($capacityIncrease);
            $lockedStand->forceFill([
                'capacity' => $targetCapacity,
                'status' => StadiumStandStatus::UNDER_CONSTRUCTION,
            ])->save();

            return StadiumStandConstruction::query()->create([
                'instance_id' => $stadium->instance_id,
                'stadium_id' => $stadium->id,
                'stadium_stand_id' => $lockedStand->id,
                'target_capacity' => $targetCapacity,
                'capacity_increase' => $capacityIncrease,
                'started_at' => $startedAt,
                'completes_at' => $startedAt->addWeeks($durationInWeeks),
            ]);
        });
    }

    public function completeForInstance(Instance $instance, CarbonImmutable $asOf): int
    {
        $completed = 0;

        StadiumStandConstruction::query()
            ->where('instance_id', $instance->id)
            ->whereDate('completes_at', '<=', $asOf->toDateString())
            ->get()
            ->each(function (StadiumStandConstruction $construction) use (&$completed): void {
                DB::transaction(function () use ($construction, &$completed): void {
                    $lockedConstruction = StadiumStandConstruction::query()
                        ->whereKey($construction->id)
                        ->lockForUpdate()
                        ->first();

                    if ($lockedConstruction === null) {
                        return;
                    }

                    $stand = StadiumStand::query()->whereKey($lockedConstruction->stadium_stand_id)->first();
                    if ($stand !== null) {
                        $stand->forceFill(['status' => StadiumStandStatus::ACTIVE])->save();
                    }

                    $lockedConstruction->delete();
                    $completed++;
                });
            });

        return $completed;
    }
}
