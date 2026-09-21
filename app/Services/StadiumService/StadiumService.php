<?php

namespace App\Services\StadiumService;

use App\ConstructionPaymentMethod;
use App\Models\BaseData\BaseCommercialCategory;
use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Models\StadiumStand;
use App\Models\StadiumStandConstruction;
use App\Repositories\StadiumRepository;
use App\Services\CommercialService\CommercialVenueSize;
use App\Services\StadiumService\Domain\BuildStadium;
use App\Services\StadiumService\Domain\CompleteStandConstruction;
use App\Services\StadiumService\Domain\StadiumBuildValidator;
use App\Services\StadiumService\Domain\StadiumCapacityManager;
use App\Services\StadiumService\Domain\StadiumStandValidator;
use App\Services\StadiumService\Domain\StartStandConstruction;
use App\Services\StadiumService\Operations\BuildCommercialVenue;
use App\Services\StadiumService\Operations\DemolishCommercialVenue;
use App\StadiumConstructionType;
use App\StadiumStandPosition;
use Carbon\CarbonImmutable;
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
        private readonly BuildStadium $buildStadium,
        private readonly StartStandConstruction $startStandConstruction,
        private readonly CompleteStandConstruction $completeStandConstruction,
        private readonly StadiumCapacityManager $stadiumCapacityManager,
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

    public function build(
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
        return $this->buildStadium->handle($stadium, $buildingType, $standId, $targetCapacity, $categoryId, $size, $paymentMethod, $lengthYears, $startedAt);
    }

    public function durationInWeeks(int $capacityIncrease): int
    {
        return $this->startStandConstruction->durationInWeeks($capacityIncrease);
    }

    public function startStandConstruction(StadiumStand $stadiumStand, int $targetCapacity, CarbonImmutable $startedAt): StadiumStandConstruction
    {
        return $this->startStandConstruction->handle($stadiumStand, $targetCapacity, $startedAt);
    }

    public function completeStandConstructionForInstance(Instance $instance, CarbonImmutable $asOf): int
    {
        return $this->completeStandConstruction->handle($instance, $asOf);
    }

    public function recalculateCapacitiesForInstance(Instance $instance): void
    {
        $this->stadiumRepository->stadiumsWithStandConstructionForInstance($instance)
            ->each(function (Stadium $stadium): void {
                $this->recalculateCapacities($stadium);
            });
    }

    public function recalculateCapacities(Stadium $stadium): void
    {
        $this->stadiumCapacityManager->recalculate($stadium);
    }
}
