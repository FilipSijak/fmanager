<?php

namespace App\Repositories;

use App\Models\BaseData\BaseCommercialCategory;
use App\Models\BaseData\BaseStadiumStandCapacityLimit;
use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Models\StadiumStand;
use App\Models\StadiumStandConstruction;
use App\Services\StadiumService\StadiumType;
use App\StadiumStandPosition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class StadiumRepository
{
    public function maximumStandsForType(StadiumType $type): int
    {
        return BaseStadiumStandCapacityLimit::query()
            ->where('stadium_type', $type->value)
            ->count();
    }

    public function maximumCapacityForStand(StadiumType $type, StadiumStandPosition $position): int
    {
        return (int) BaseStadiumStandCapacityLimit::query()
            ->where('stadium_type', $type->value)
            ->where('position', $position->value)
            ->value('maximum_capacity');
    }

    /** @return Collection<int, StadiumStand> */
    public function standsForValidation(Stadium $stadium): Collection
    {
        return $stadium->stands()->with('construction')->get();
    }

    /** @return Collection<int, StadiumCommercialVenue> */
    public function commercialVenuesForStadium(Stadium $stadium): Collection
    {
        return StadiumCommercialVenue::query()
            ->with('category')
            ->whereBelongsTo($stadium)
            ->where('instance_id', $stadium->instance_id)
            ->get();
    }

    /** @return Collection<int, BaseCommercialCategory> */
    public function buildableCommercialCategoriesForStadium(Stadium $stadium): Collection
    {
        if ($this->commercialVenueCount($stadium) >= $stadium->commercial_limit) {
            return new Collection;
        }

        return BaseCommercialCategory::query()
            ->where('is_active', true)
            ->whereNotIn('id', $stadium->commercialVenues()->select('category_id'))
            ->whereExists(function (Builder $query) use ($stadium): void {
                $query->selectRaw('1')
                    ->from('base_commercial_category_stadium_type')
                    ->whereColumn('base_commercial_category_stadium_type.category_id', 'base_commercial_categories.id')
                    ->where('base_commercial_category_stadium_type.stadium_type', $stadium->type->value);
            })
            ->orderBy('name')
            ->get();
    }

    public function lockStadium(int $stadiumId): Stadium
    {
        return Stadium::query()->whereKey($stadiumId)->lockForUpdate()->firstOrFail();
    }

    public function categoryIsAvailableForType(int $categoryId, StadiumType $type): bool
    {
        return DB::table('base_commercial_category_stadium_type')
            ->where('category_id', $categoryId)
            ->where('stadium_type', $type->value)
            ->exists();
    }

    public function commercialVenueExists(Stadium $stadium, int $categoryId): bool
    {
        return StadiumCommercialVenue::query()
            ->whereBelongsTo($stadium)
            ->where('instance_id', $stadium->instance_id)
            ->where('category_id', $categoryId)
            ->exists();
    }

    public function commercialVenueCount(Stadium $stadium): int
    {
        return StadiumCommercialVenue::query()->whereBelongsTo($stadium)
            ->where('instance_id', $stadium->instance_id)->count();
    }

    public function findCategoryOrFail(int $categoryId): BaseCommercialCategory
    {
        return BaseCommercialCategory::query()->findOrFail($categoryId);
    }

    public function createCommercialVenue(array $attributes): StadiumCommercialVenue
    {
        return StadiumCommercialVenue::query()->create($attributes);
    }

    public function commercialVenueForStadium(Stadium $stadium, int $venueId): ?StadiumCommercialVenue
    {
        return StadiumCommercialVenue::query()
            ->with('category')
            ->whereBelongsTo($stadium)
            ->where('instance_id', $stadium->instance_id)
            ->whereKey($venueId)
            ->first();
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
            ->with('construction')
            ->whereBelongsTo($stadium)
            ->get();
    }
}
