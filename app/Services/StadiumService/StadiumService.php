<?php

namespace App\Services\StadiumService;

use App\ConstructionPaymentMethod;
use App\Models\BaseData\BaseCommercialCategory;
use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Models\StadiumStand;
use App\Models\StadiumStandConstruction;
use App\Services\CommercialService\CommercialVenueSize;
use App\Services\StadiumService\Domain\StadiumCapacityManager;
use App\Services\StadiumService\Domain\StadiumCommercialOperations;
use App\Services\StadiumService\Domain\StadiumConstructionOperations;
use App\Services\StadiumService\Domain\StadiumInformation;
use App\StadiumConstructionType;
use App\StadiumStandPosition;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class StadiumService
{
    public function __construct(
        private readonly StadiumInformation $stadiumInformation,
        private readonly StadiumCommercialOperations $stadiumCommercialOperations,
        private readonly StadiumConstructionOperations $stadiumConstructionOperations,
        private readonly StadiumCapacityManager $stadiumCapacityManager,
    ) {}

    public function typeForCapacity(int $capacity): StadiumType
    {
        return $this->stadiumInformation->typeForCapacity($capacity);
    }

    public function commercialLimitForType(StadiumType $type): int
    {
        return $this->stadiumInformation->commercialLimitForType($type);
    }

    public function maximumCapacityForStadium(Stadium $stadium): int
    {
        return $this->stadiumInformation->maximumCapacityForStadium($stadium);
    }

    public function maximumStandsForStadium(Stadium $stadium): int
    {
        return $this->stadiumInformation->maximumStandsForStadium($stadium);
    }

    public function maximumCapacityForStand(Stadium $stadium, StadiumStandPosition $position): int
    {
        return $this->stadiumInformation->maximumCapacityForStand($stadium, $position);
    }

    public function validateStadiumStandPosition(Stadium $stadium, StadiumStandPosition $position): void
    {
        $this->stadiumInformation->validateStadiumStandPosition($stadium, $position);
    }

    public function validateStadiumCapacity(Stadium $stadium, int $capacity): void
    {
        $this->stadiumInformation->validateStadiumCapacity($stadium, $capacity);
    }

    public function validateStadiumBuild(Stadium $stadium): void
    {
        $this->stadiumInformation->validateStadiumBuild($stadium);
    }

    public function commercialVenuesForStadium(Stadium $stadium): Collection
    {
        return $this->stadiumInformation->commercialVenuesForStadium($stadium);
    }

    /** @return Collection<int, BaseCommercialCategory> */
    public function buildableCommercialCategoriesForStadium(Stadium $stadium): Collection
    {
        return $this->stadiumInformation->buildableCommercialCategoriesForStadium($stadium);
    }

    public function buildCommercialVenue(Stadium $stadium, int $categoryId, CommercialVenueSize $size): StadiumCommercialVenue
    {
        return $this->stadiumCommercialOperations->build($stadium, $categoryId, $size);
    }

    public function stadiumExpansionCost(Stadium $stadium, int $additionalCapacity): int
    {
        return $this->stadiumInformation->stadiumExpansionCost($stadium, $additionalCapacity);
    }

    public function demolishCommercialVenue(Stadium $stadium, int $venueId): int
    {
        return $this->stadiumCommercialOperations->demolish($stadium, $venueId);
    }

    public function buildConstruction(
        Stadium $stadium,
        StadiumConstructionType $buildingType,
        ?int $standId,
        ?int $targetCapacity,
        ?int $categoryId,
        ?CommercialVenueSize $size,
        ConstructionPaymentMethod $paymentMethod,
        int $lengthYears,
        CarbonImmutable $startedAt,
    ): StadiumStandConstruction|StadiumCommercialVenue {
        return $this->stadiumConstructionOperations->buildConstruction($stadium, $buildingType, $standId, $targetCapacity, $categoryId, $size, $paymentMethod, $lengthYears, $startedAt);
    }

    public function durationInWeeks(int $capacityIncrease): int
    {
        return $this->stadiumConstructionOperations->durationInWeeks($capacityIncrease);
    }

    public function startStandConstruction(StadiumStand $stadiumStand, int $targetCapacity, CarbonImmutable $startedAt): StadiumStandConstruction
    {
        return $this->stadiumConstructionOperations->startStandConstruction($stadiumStand, $targetCapacity, $startedAt);
    }

    public function completeStandConstructionForInstance(Instance $instance, CarbonImmutable $asOf): int
    {
        return $this->stadiumConstructionOperations->completeForInstance($instance, $asOf);
    }

    public function recalculateCapacitiesForInstance(Instance $instance): void
    {
        $this->stadiumCapacityManager->recalculateForInstance($instance);
    }

    public function recalculateCapacities(Stadium $stadium): void
    {
        $this->stadiumCapacityManager->recalculate($stadium);
    }
}
