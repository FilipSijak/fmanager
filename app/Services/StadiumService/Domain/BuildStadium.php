<?php

namespace App\Services\StadiumService\Domain;

use App\ConstructionPaymentMethod;
use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Models\StadiumStandConstruction;
use App\Repositories\StadiumRepository;
use App\Services\CommercialService\CommercialVenueSize;
use App\Services\CommercialService\VenueConstructionCostCalculator;
use App\Services\FinanceService\FinanceService;
use App\Services\StadiumService\Operations\BuildCommercialVenue;
use App\Services\StadiumService\StadiumExpansionCostCalculator;
use App\StadiumConstructionType;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class BuildStadium
{
    public function __construct(
        private readonly StadiumRepository $stadiumRepository,
        private readonly StadiumExpansionCostCalculator $stadiumExpansionCostCalculator,
        private readonly BuildCommercialVenue $buildCommercialVenue,
        private readonly StartStandConstruction $startStandConstruction,
        private readonly VenueConstructionCostCalculator $venueConstructionCostCalculator,
        private readonly FinanceService $financeService,
    ) {}

    public function handle(
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
        return DB::transaction(function () use ($stadium, $buildingType, $standId, $targetCapacity, $categoryId, $size, $paymentMethod, $lengthYears, $startedAt): StadiumStandConstruction|StadiumCommercialVenue {
            $lockedStadium = $this->stadiumRepository->lockStadium($stadium->id);
            $cost = $this->constructionCost($lockedStadium, $buildingType, $standId, $targetCapacity, $categoryId, $size);

            if ($paymentMethod === ConstructionPaymentMethod::CASH) {
                $this->financeService->payForConstruction($cost, $startedAt);
            }

            $construction = $buildingType === StadiumConstructionType::STAND
                ? $this->startStandConstruction->handle($this->stadiumRepository->standForStadium($lockedStadium, $standId), $targetCapacity, $startedAt)
                : $this->buildCommercialVenue->handle($lockedStadium, $categoryId, $size);

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

            return $this->stadiumExpansionCostCalculator->calculate($stadium, $targetCapacity - (int) $stand->capacity);
        }

        if ($categoryId === null || $size === null) {
            throw new DomainException('Commercial venue construction details are required.');
        }

        return $this->venueConstructionCostCalculator->calculate($stadium, $this->stadiumRepository->findCategoryOrFail($categoryId), $size);
    }
}
