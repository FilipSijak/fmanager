<?php

namespace App\Services\StadiumService;

use App\ConstructionPaymentMethod;
use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Models\StadiumStandConstruction;
use App\Repositories\StadiumRepository;
use App\Services\CommercialService\CommercialVenueSize;
use App\Services\CommercialService\VenueConstructionCostCalculator;
use App\Services\FinanceService\FinanceService;
use App\StadiumConstructionType;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class StadiumConstructionService
{
    public function __construct(
        private readonly StadiumRepository $stadiumRepository,
        private readonly StadiumService $stadiumService,
        private readonly StadiumStandConstructionService $standConstructionService,
        private readonly VenueConstructionCostCalculator $venueConstructionCostCalculator,
        private readonly FinanceService $financeService,
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
        return DB::transaction(function () use (
            $stadium,
            $buildingType,
            $standId,
            $targetCapacity,
            $categoryId,
            $size,
            $paymentMethod,
            $lengthYears,
            $startedAt,
        ): StadiumStandConstruction|StadiumCommercialVenue {
            $cost = $this->constructionCost($stadium, $buildingType, $standId, $targetCapacity, $categoryId, $size);

            if ($paymentMethod === ConstructionPaymentMethod::CASH) {
                $this->financeService->payForConstruction($cost, $startedAt);
            }

            $construction = $buildingType === StadiumConstructionType::STAND
                ? $this->standConstructionService->startConstruction(
                    $this->stadiumRepository->standForStadium($stadium, $standId),
                    $targetCapacity,
                    $startedAt,
                )
                : $this->stadiumService->buildCommercialVenue($stadium, $categoryId, $size);

            if ($paymentMethod === ConstructionPaymentMethod::MORTGAGE) {
                $this->financeService->takeOutMortgageLoan(
                    $cost,
                    $lengthYears,
                    $startedAt,
                    $construction instanceof StadiumStandConstruction ? $construction->id : null,
                    $construction instanceof StadiumCommercialVenue ? $construction->id : null,
                );
            }

            return $construction;
        });
    }

    private function constructionCost(
        Stadium $stadium,
        StadiumConstructionType $buildingType,
        ?int $standId,
        ?int $targetCapacity,
        ?int $categoryId,
        ?CommercialVenueSize $size,
    ): int {
        if ($buildingType === StadiumConstructionType::STAND) {
            if ($standId === null || $targetCapacity === null) {
                throw new DomainException('Stand construction details are required.');
            }

            $stand = $this->stadiumRepository->standForStadium($stadium, $standId);
            $capacityIncrease = $targetCapacity - (int) $stand->capacity;

            return $this->stadiumService->stadiumExpansionCost($stadium, $capacityIncrease);
        }

        if ($categoryId === null || $size === null) {
            throw new DomainException('Commercial venue construction details are required.');
        }

        $category = $this->stadiumRepository->findCategoryOrFail($categoryId);

        return $this->venueConstructionCostCalculator->calculate($stadium, $category, $size);
    }
}
