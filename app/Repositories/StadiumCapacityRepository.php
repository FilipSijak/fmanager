<?php

namespace App\Repositories;

use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumStand;
use App\Models\StadiumStandConstruction;
use App\StadiumStandStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StadiumCapacityRepository
{
    /** @return list<array{capacity: int, available: bool, construction_capacity: int}> */
    public function standsAtDate(Stadium $stadium, CarbonInterface $date): array
    {
        return $stadium->stands()->with(['constructionHistory' => fn (HasMany $query): HasMany => $query->orderBy('started_at')->orderBy('id')])
            ->get()->map(function (StadiumStand $stand) use ($date): array {
                $construction = $stand->constructionHistory->first(fn (StadiumStandConstruction $work): bool => $work->started_at->lessThanOrEqualTo($date)
                    && ($work->completed_at === null || $work->completed_at->greaterThan($date)));
                if ($construction !== null) {
                    $capacity = max(0, $construction->target_capacity - $construction->capacity_increase);

                    return ['capacity' => $capacity, 'available' => false, 'construction_capacity' => $capacity];
                }
                $futureConstruction = $stand->constructionHistory->first(fn (StadiumStandConstruction $work): bool => $work->started_at->greaterThan($date));
                if ($futureConstruction !== null) {
                    return ['capacity' => max(0, $futureConstruction->target_capacity - $futureConstruction->capacity_increase),
                        'available' => true, 'construction_capacity' => 0];
                }
                $available = $stand->constructionHistory->isNotEmpty() || $stand->status === StadiumStandStatus::ACTIVE;

                return ['capacity' => (int) $stand->capacity, 'available' => $available,
                    'construction_capacity' => $available ? 0 : (int) $stand->capacity];
            })->all();
    }

    /** @return Collection<int, Stadium> */
    public function stadiumsWithStandConstructionForInstance(Instance $instance): Collection
    {
        $stadiumIds = StadiumStandConstruction::query()
            ->where('instance_id', $instance->id)
            ->distinct()
            ->pluck('stadium_id');

        if ($stadiumIds->isEmpty()) {
            return new Collection;
        }

        return Stadium::query()
            ->where('instance_id', $instance->id)
            ->whereIn('id', $stadiumIds)
            ->get();
    }

    /** @return Collection<int, StadiumStand> */
    public function standsForCapacity(Stadium $stadium): Collection
    {
        return StadiumStand::query()
            ->withConstruction()
            ->whereBelongsTo($stadium)
            ->get();
    }
}
