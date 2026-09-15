<?php

namespace App\Services\StadiumService;

use App\Models\BaseData\BaseCommercialCategory;
use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Repositories\StadiumRepository;
use App\Services\CommercialService\CommercialVenueSize;
use App\Services\StadiumService\Operations\BuildCommercialVenue;
use App\Services\StadiumService\Operations\DemolishCommercialVenue;
use App\StadiumStandPosition;
use App\StadiumStandStatus;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

class StadiumService
{
    public function __construct(
        private readonly StadiumExpansionCostCalculator $stadiumExpansionCostCalculator,
        private readonly StadiumRepository $stadiumRepository,
        private readonly BuildCommercialVenue $buildCommercialVenue,
        private readonly DemolishCommercialVenue $demolishCommercialVenue,
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
        return $this->stadiumRepository->maximumStandsForType($stadium->type);
    }

    public function maximumCapacityForStand(Stadium $stadium, StadiumStandPosition $position): int
    {
        return $this->stadiumRepository->maximumCapacityForStand($stadium->type, $position);
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
        $stands = $this->stadiumRepository->standsForValidation($stadium);
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
        return $this->stadiumRepository->commercialVenuesForStadium($stadium);
    }

    /**
     * @return Collection<int, BaseCommercialCategory>
     */
    public function buildableCommercialCategoriesForStadium(Stadium $stadium): Collection
    {
        return $this->stadiumRepository->buildableCommercialCategoriesForStadium($stadium);
    }

    public function buildCommercialVenue(Stadium $stadium, int $categoryId, CommercialVenueSize $size): StadiumCommercialVenue
    {
        return $this->buildCommercialVenue->handle($stadium, $categoryId, $size);
    }

    public function stadiumExpansionCost(Stadium $stadium, int $additionalCapacity): int
    {
        return $this->stadiumExpansionCostCalculator->calculate($stadium, $additionalCapacity);
    }

    public function demolishCommercialVenue(Stadium $stadium, int $venueId): int
    {
        return $this->demolishCommercialVenue->handle($stadium, $venueId);
    }

    public function recalculateCapacitiesForInstance(Instance $instance): void
    {
        $this->stadiumRepository->stadiumsWithStandConstructionForInstance($instance)
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
        $stands = $this->stadiumRepository->standsForCapacity($stadium);

        $activeCapacity = $this->activeCapacityForStands($stands);

        $stadium->forceFill([
            'capacity' => (int) $stands->sum('capacity'),
            'active_capacity' => (int) $activeCapacity,
        ])->saveQuietly();
    }
}
