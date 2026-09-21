<?php

namespace App\Services\StadiumService\Domain;

use App\Models\BaseData\BaseCommercialCategory;
use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Repositories\StadiumRepository;
use App\Services\StadiumService\StadiumExpansionCostCalculator;
use App\Services\StadiumService\StadiumType;
use App\StadiumStandPosition;
use Illuminate\Database\Eloquent\Collection;

class StadiumInformation
{
    public function __construct(
        private readonly StadiumRepository $stadiumRepository,
        private readonly StadiumExpansionCostCalculator $stadiumExpansionCostCalculator,
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

    /** @return Collection<int, StadiumCommercialVenue> */
    public function commercialVenuesForStadium(Stadium $stadium): Collection
    {
        return $this->stadiumRepository->commercialVenuesForStadium($stadium);
    }

    /** @return Collection<int, BaseCommercialCategory> */
    public function buildableCommercialCategoriesForStadium(Stadium $stadium): Collection
    {
        return $this->stadiumRepository->buildableCommercialCategoriesForStadium($stadium);
    }

    public function stadiumExpansionCost(Stadium $stadium, int $additionalCapacity): int
    {
        return $this->stadiumExpansionCostCalculator->calculate($stadium, $additionalCapacity);
    }
}
