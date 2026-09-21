<?php

namespace App\Repositories;

use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumStand;
use App\Models\StadiumStandConstruction;
use Illuminate\Database\Eloquent\Collection;

class StadiumCapacityRepository
{
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
