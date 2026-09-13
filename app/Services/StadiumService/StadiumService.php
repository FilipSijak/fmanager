<?php

namespace App\Services\StadiumService;

use App\Models\BaseData\BaseCommercialCategory;
use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Models\StadiumStand;
use App\Services\CommercialService\CommercialVenueSize;
use App\StadiumStandPosition;
use App\StadiumStandStatus;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class StadiumService
{
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
        return count(StadiumStandPosition::cases());
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
        $stands = $stadium->stands()->get();
        $capacity = (int) $stands->sum('capacity');
        $activeCapacity = (int) $stands
            ->where('status', StadiumStandStatus::ACTIVE)
            ->sum('capacity');

        if ($stands->count() > $this->maximumStandsForStadium($stadium)) {
            throw new DomainException('A stadium cannot have more than eight stands.');
        }

        if ($stands->contains(fn (StadiumStand $stand): bool => $stand->capacity !== null && $stand->capacity < 0)) {
            throw new DomainException('Stadium stand capacity cannot be negative.');
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
            ]);
        });
    }

    public function recalculateCapacities(Stadium $stadium): void
    {
        $stands = StadiumStand::query()->whereBelongsTo($stadium);

        $stadium->forceFill([
            'capacity' => (int) (clone $stands)->sum('capacity'),
            'active_capacity' => (int) $stands
                ->where('status', StadiumStandStatus::ACTIVE->value)
                ->sum('capacity'),
        ])->saveQuietly();
    }
}
