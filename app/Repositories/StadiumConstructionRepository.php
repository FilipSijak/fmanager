<?php

namespace App\Repositories;

use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumStand;
use App\Models\StadiumStandConstruction;
use App\StadiumStandStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class StadiumConstructionRepository
{
    public function lockStand(int $standId): StadiumStand
    {
        return StadiumStand::query()->whereKey($standId)->lockForUpdate()->firstOrFail();
    }

    public function hasConstructionInProgress(StadiumStand $stadiumStand): bool
    {
        return StadiumStandConstruction::query()
            ->where('stadium_stand_id', $stadiumStand->id)
            ->whereNull('completed_at')
            ->exists();
    }

    /** @param array<string, mixed> $attributes */
    public function createStandConstruction(array $attributes): StadiumStandConstruction
    {
        return StadiumStandConstruction::query()->create($attributes);
    }

    /** @return Collection<int, StadiumStandConstruction> */
    public function dueForInstance(Instance $instance, CarbonImmutable $asOf): Collection
    {
        return StadiumStandConstruction::query()
            ->where('instance_id', $instance->id)
            ->whereNull('completed_at')
            ->whereDate('completes_at', '<=', $asOf->toDateString())
            ->get();
    }

    public function lockConstruction(int $constructionId): ?StadiumStandConstruction
    {
        return StadiumStandConstruction::query()
            ->whereKey($constructionId)
            ->lockForUpdate()
            ->first();
    }

    public function activateStand(int $standId): void
    {
        $stand = StadiumStand::query()->whereKey($standId)->first();

        if ($stand !== null) {
            $stand->forceFill(['status' => StadiumStandStatus::ACTIVE])->saveQuietly();
        }
    }

    public function markCompleted(StadiumStandConstruction $construction, CarbonImmutable $asOf): void
    {
        $construction->update(['completed_at' => $asOf->toDateString()]);
    }

    public function stadiumForStand(StadiumStand $stadiumStand): Stadium
    {
        return $stadiumStand->stadium()->firstOrFail();
    }

    public function updateStandCapacity(StadiumStand $stadiumStand, int $capacity): void
    {
        $stadiumStand->forceFill(['capacity' => $capacity, 'status' => StadiumStandStatus::UNDER_CONSTRUCTION])->save();
    }
}
