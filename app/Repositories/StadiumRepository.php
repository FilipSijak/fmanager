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

class StadiumRepository
{
    public function maximumStandsForType(StadiumType $type): int
    {
        return BaseStadiumStandCapacityLimit::query()->forType($type)->count();
    }

    public function maximumCapacityForStand(StadiumType $type, StadiumStandPosition $position): int
    {
        return (int) BaseStadiumStandCapacityLimit::query()->forType($type)->forPosition($position)->value('maximum_capacity');
    }

    /** @return Collection<int, StadiumStand> */
    public function standsForValidation(Stadium $stadium): Collection
    {
        return StadiumStand::query()->withConstruction()->whereBelongsTo($stadium)->get();
    }

    /** @return Collection<int, StadiumCommercialVenue> */
    public function commercialVenuesForStadium(Stadium $stadium): Collection
    {
        return StadiumCommercialVenue::query()->forStadium($stadium)->with('category')->get();
    }

    /** @return Collection<int, BaseCommercialCategory> */
    public function buildableCommercialCategoriesForStadium(Stadium $stadium): Collection
    {
        if ($this->commercialVenueCount($stadium) >= $stadium->commercial_limit) {
            return new Collection;
        }

        return BaseCommercialCategory::query()
            ->active()
            ->whereNotIn('id', StadiumCommercialVenue::query()->forStadium($stadium)->select('category_id'))
            ->availableForStadiumType($stadium->type)
            ->orderBy('name')
            ->get();
    }

    public function stadiumById(int $stadiumId): ?Stadium
    {
        return Stadium::query()->whereKey($stadiumId)->first();
    }

    public function lockStadium(int $stadiumId): Stadium
    {
        return Stadium::query()->whereKey($stadiumId)->lockForUpdate()->firstOrFail();
    }

    public function categoryIsAvailableForType(int $categoryId, StadiumType $type): bool
    {
        return BaseCommercialCategory::query()
            ->active()
            ->whereKey($categoryId)
            ->availableForStadiumType($type)
            ->exists();
    }

    public function commercialVenueExists(Stadium $stadium, int $categoryId): bool
    {
        return StadiumCommercialVenue::query()
            ->forStadium($stadium)
            ->where('category_id', $categoryId)
            ->exists();
    }

    public function commercialVenueCount(Stadium $stadium): int
    {
        return StadiumCommercialVenue::query()->forStadium($stadium)->count();
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
        return StadiumCommercialVenue::query()->forStadium($stadium)->with('category')->whereKey($venueId)->first();
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
        return StadiumStand::query()->withConstruction()->whereBelongsTo($stadium)
            ->get();
    }
}
