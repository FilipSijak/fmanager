<?php

namespace App\Services\StadiumService;

use App\Models\BaseData\BaseCommercialCategory;
use App\Models\BaseData\BaseStadiumStandCapacityLimit;
use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Models\StadiumStand;
use App\Models\StadiumStandConstruction;
use App\Services\CommercialService\CommercialVenueSize;
use App\Services\CommercialService\VenueConstructionCostCalculator;
use App\StadiumStandPosition;
use App\StadiumStandStatus;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class StadiumService
{
    public function __construct(
        private readonly VenueConstructionCostCalculator $venueConstructionCostCalculator,
        private readonly StadiumExpansionCostCalculator $stadiumExpansionCostCalculator,
    ) {}

    public function typeForCapacity(int $capacity): StadiumType
    {
        return StadiumType::fromCapacity($capacity);
    }

    public function commercialLimitForType(StadiumType $type): int
    {
        return $type->commercialLimit();
    }

    public function maximumCapacityForStadium(Stadium $stadium): int
    {
        return $stadium->type->maximumCapacity();
    }

    public function maximumStandsForStadium(Stadium $stadium): int
    {
        return BaseStadiumStandCapacityLimit::query()
            ->where('stadium_type', $stadium->type->value)
            ->count();
    }

    public function maximumCapacityForStand(Stadium $stadium, StadiumStandPosition $position): int
    {
        return (int) BaseStadiumStandCapacityLimit::query()
            ->where('stadium_type', $stadium->type->value)
            ->where('position', $position->value)
            ->value('maximum_capacity');
    }

    public function validateStadiumStandPosition(Stadium $stadium, StadiumStandPosition $position): void
    {
        if ($this->maximumCapacityForStand($stadium, $position) === 0) {
            if ($stadium->type->allowsCornerStands() === false && in_array($position, [
                StadiumStandPosition::NORTH_EAST,
                StadiumStandPosition::SOUTH_EAST,
                StadiumStandPosition::SOUTH_WEST,
                StadiumStandPosition::NORTH_WEST,
            ], true)) {
                throw new DomainException('Village and Local stadiums cannot build corner stands.');
            }

            throw new DomainException('This stand position is not available for the stadium type.');
        }
    }

    public function validateStadiumCapacity(Stadium $stadium, int $capacity): void
    {
        if ($capacity < 0) {
            throw new DomainException('Stadium capacity cannot be negative.');
        }

        if ($capacity > $this->maximumCapacityForStadium($stadium)) {
            throw new DomainException('Stadium capacity exceeds the maximum for its stadium type.');
        }
    }

    public function validateStadiumBuild(Stadium $stadium): void
    {
        $stands = $stadium->stands()->with('construction')->get();
        $capacity = (int) $stands->sum('capacity');
        $activeCapacity = $this->activeCapacityForStands($stands);

        foreach ($stands as $stand) {
            if ($stand->position === null) {
                throw new DomainException('Stadium stands must have a position.');
            }

            $this->validateStadiumStandPosition($stadium, $stand->position);
            $maximumStandCapacity = $this->maximumCapacityForStand($stadium, $stand->position);

            if ($stand->capacity !== null && $stand->capacity < 0) {
                throw new DomainException('Stadium stand capacity cannot be negative.');
            }

            if ($stand->capacity !== null && $stand->capacity % 1000 !== 0) {
                throw new DomainException('Stadium stand capacity must be a multiple of 1,000 seats.');
            }

            if ($stand->capacity !== null && $stand->capacity > $maximumStandCapacity) {
                throw new DomainException('Stadium stand capacity exceeds the maximum for its position.');
            }
        }

        if ($stands->count() > $this->maximumStandsForStadium($stadium)) {
            throw new DomainException('A stadium cannot have more stands than its type allows.');
        }

        $this->validateStadiumCapacity($stadium, $capacity);

        if ((int) $stadium->capacity !== $capacity) {
            throw new DomainException('Stadium capacity does not match its stands.');
        }

        if ((int) $stadium->active_capacity !== $activeCapacity) {
            throw new DomainException('Stadium active capacity does not match its active stands.');
        }
    }

    public function commercialVenuesForStadium(Stadium $stadium): Collection
    {
        return StadiumCommercialVenue::query()
            ->with('category')
            ->whereBelongsTo($stadium)
            ->get();
    }

    /**
     * @return Collection<int, BaseCommercialCategory>
     */
    public function buildableCommercialCategoriesForStadium(Stadium $stadium): Collection
    {
        if ($stadium->commercialVenues()->count() >= $stadium->commercial_limit) {
            return new Collection;
        }

        $builtCategoryIds = $stadium->commercialVenues()->select('category_id');

        return BaseCommercialCategory::query()
            ->where('is_active', true)
            ->whereNotIn('id', $builtCategoryIds)
            ->whereExists(function (Builder $query) use ($stadium): void {
                $query->selectRaw('1')
                    ->from('base_commercial_category_stadium_type')
                    ->whereColumn('base_commercial_category_stadium_type.category_id', 'base_commercial_categories.id')
                    ->where('base_commercial_category_stadium_type.stadium_type', $stadium->type->value);
            })
            ->orderBy('name')
            ->get();
    }

    public function buildCommercialVenue(Stadium $stadium, int $categoryId, CommercialVenueSize $size): StadiumCommercialVenue
    {
        return DB::transaction(function () use ($stadium, $categoryId, $size): StadiumCommercialVenue {
            $lockedStadium = Stadium::query()->whereKey($stadium->id)->lockForUpdate()->firstOrFail();
            $stadiumType = $lockedStadium->type;

            $categoryIsAvailable = DB::table('base_commercial_category_stadium_type')
                ->where('category_id', $categoryId)
                ->where('stadium_type', $stadiumType->value)
                ->exists();

            if (! $categoryIsAvailable) {
                throw new DomainException('This commercial category is not available for the stadium type.');
            }

            $categoryAlreadyBuilt = StadiumCommercialVenue::query()
                ->where('instance_id', $lockedStadium->instance_id)
                ->where('stadium_id', $lockedStadium->id)
                ->where('category_id', $categoryId)
                ->exists();

            if ($categoryAlreadyBuilt) {
                throw new DomainException('This commercial venue category has already been built at the stadium.');
            }

            $venueCount = StadiumCommercialVenue::query()
                ->where('instance_id', $lockedStadium->instance_id)
                ->where('stadium_id', $lockedStadium->id)
                ->count();

            if ($venueCount >= $lockedStadium->commercial_limit) {
                throw new DomainException('The stadium has reached its commercial venue limit.');
            }

            return StadiumCommercialVenue::query()->create([
                'instance_id' => $lockedStadium->instance_id,
                'stadium_id' => $lockedStadium->id,
                'category_id' => $categoryId,
                'size' => $size,
                'build_cost' => $this->venueConstructionCostCalculator->calculate(
                    $lockedStadium,
                    BaseCommercialCategory::query()->findOrFail($categoryId),
                    $size,
                ),
            ]);
        });
    }

    public function stadiumExpansionCost(Stadium $stadium, int $additionalCapacity): int
    {
        return $this->stadiumExpansionCostCalculator->calculate($stadium, $additionalCapacity);
    }

    public function demolishCommercialVenue(Stadium $stadium, int $venueId): int
    {
        return DB::transaction(function () use ($stadium, $venueId): int {
            $lockedStadium = Stadium::query()->whereKey($stadium->id)->lockForUpdate()->firstOrFail();
            $venue = StadiumCommercialVenue::query()
                ->with('category')
                ->where('instance_id', $lockedStadium->instance_id)
                ->where('stadium_id', $lockedStadium->id)
                ->whereKey($venueId)
                ->first();

            if ($venue === null) {
                throw new DomainException('This commercial venue does not exist at the stadium.');
            }

            $buildCost = $venue->build_cost ?? $this->venueConstructionCostCalculator->calculate(
                $lockedStadium,
                $venue->category,
                $venue->size,
            );
            $demolitionCost = $this->venueConstructionCostCalculator->demolitionCost($buildCost);

            $venue->delete();

            return $demolitionCost;
        });
    }

    public function recalculateCapacitiesForInstance(Instance $instance): void
    {
        $stadiumIds = StadiumStandConstruction::query()
            ->where('instance_id', $instance->id)
            ->distinct()
            ->pluck('stadium_id');

        if ($stadiumIds->isEmpty()) {
            return;
        }

        Stadium::query()
            ->where('instance_id', $instance->id)
            ->whereIn('id', $stadiumIds)
            ->get()
            ->each(function (Stadium $stadium): void {
                $this->recalculateCapacities($stadium);
            });
    }

    private function activeCapacityForStands(Collection $stands): int
    {
        return (int) $stands
            ->where('status', StadiumStandStatus::ACTIVE)
            ->sum('capacity');
    }

    public function recalculateCapacities(Stadium $stadium): void
    {
        $stands = StadiumStand::query()
            ->with('construction')
            ->whereBelongsTo($stadium)
            ->get();

        $activeCapacity = $this->activeCapacityForStands($stands);

        $stadium->forceFill([
            'capacity' => (int) $stands->sum('capacity'),
            'active_capacity' => (int) $activeCapacity,
        ])->saveQuietly();
    }
}
