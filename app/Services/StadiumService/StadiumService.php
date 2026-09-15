<?php

namespace App\Services\StadiumService;

use App\Models\BaseData\BaseCommercialCategory;
use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Repositories\StadiumRepository;
use App\Services\CommercialService\CommercialVenueSize;
use App\Services\StadiumService\Domain\StadiumBuildValidator;
use App\Services\StadiumService\Domain\StadiumStandValidator;
use App\Services\StadiumService\Operations\BuildCommercialVenue;
use App\Services\StadiumService\Operations\DemolishCommercialVenue;
use App\StadiumStandPosition;
use App\StadiumStandStatus;
use Illuminate\Database\Eloquent\Collection;

class StadiumService
{
    public function __construct(
        private readonly StadiumExpansionCostCalculator $stadiumExpansionCostCalculator,
        private readonly StadiumRepository $stadiumRepository,
        private readonly BuildCommercialVenue $buildCommercialVenue,
        private readonly DemolishCommercialVenue $demolishCommercialVenue,
        private readonly StadiumBuildValidator $stadiumBuildValidator,
        private readonly StadiumStandValidator $stadiumStandValidator,
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
        $this->stadiumStandValidator->validatePosition($stadium, $position);
    }

    public function validateStadiumCapacity(Stadium $stadium, int $capacity): void
    {
        $this->stadiumBuildValidator->validateCapacity($stadium, $capacity);
    }

    public function validateStadiumBuild(Stadium $stadium): void
    {
        $this->stadiumBuildValidator->validate($stadium);
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
