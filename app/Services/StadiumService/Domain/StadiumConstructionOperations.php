<?php

namespace App\Services\StadiumService\Domain;

use App\ConstructionPaymentMethod;
use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Models\StadiumStand;
use App\Models\StadiumStandConstruction;
use App\Services\CommercialService\CommercialVenueSize;
use App\StadiumConstructionType;
use Carbon\CarbonImmutable;

class StadiumConstructionOperations
{
    public function __construct(
        private readonly BuildStadium $buildStadium,
        private readonly StartStandConstruction $startStandConstruction,
        private readonly CompleteStandConstruction $completeStandConstruction,
    ) {}

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

    public function completeForInstance(Instance $instance, CarbonImmutable $asOf): int
    {
        return $this->completeStandConstruction->handle($instance, $asOf);
    }
}
