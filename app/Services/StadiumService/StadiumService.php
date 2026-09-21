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
use App\Services\CommercialService\VenueConstructionCostCalculator;
use App\Services\FinanceService\FinanceService;
use App\Services\StadiumService\Domain\StadiumBuildValidator;
use App\Services\StadiumService\Domain\StadiumStandValidator;
use App\Services\StadiumService\Operations\BuildCommercialVenue;
use App\Services\StadiumService\Operations\DemolishCommercialVenue;
use App\StadiumConstructionType;
use App\StadiumStandPosition;
use App\StadiumStandStatus;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class StadiumService
{
    public function __construct(
        private readonly StadiumExpansionCostCalculator $stadiumExpansionCostCalculator,
        private readonly StadiumRepository $stadiumRepository,
        private readonly BuildCommercialVenue $buildCommercialVenue,
        private readonly DemolishCommercialVenue $demolishCommercialVenue,
        private readonly StadiumBuildValidator $stadiumBuildValidator,
        private readonly StadiumStandValidator $stadiumStandValidator,
        private readonly VenueConstructionCostCalculator $venueConstructionCostCalculator,
        private readonly FinanceService $financeService,
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
        return DB::transaction(function () use ($stadium, $buildingType, $standId, $targetCapacity, $categoryId, $size, $paymentMethod, $lengthYears, $startedAt): StadiumStandConstruction|StadiumCommercialVenue {
            $cost = $this->constructionCost($stadium, $buildingType, $standId, $targetCapacity, $categoryId, $size);

            if ($paymentMethod === ConstructionPaymentMethod::CASH) {
                $this->financeService->payForConstruction($cost, $startedAt);
            }

            $construction = $buildingType === StadiumConstructionType::STAND
                ? $this->startStandConstruction($this->stadiumRepository->standForStadium($stadium, $standId), $targetCapacity, $startedAt)
                : $this->buildCommercialVenue($stadium, $categoryId, $size);

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

    public function durationInWeeks(int $capacityIncrease): int
    {
        if ($capacityIncrease <= 0 || $capacityIncrease % 1000 !== 0) {
            throw new DomainException('Stadium construction capacity must be a positive multiple of 1,000 seats.');
        }

        return intdiv($capacityIncrease, 1000);
    }

    public function startStandConstruction(StadiumStand $stadiumStand, int $targetCapacity, CarbonImmutable $startedAt): StadiumStandConstruction
    {
        return DB::transaction(function () use ($stadiumStand, $targetCapacity, $startedAt): StadiumStandConstruction {
            $lockedStand = StadiumStand::query()->whereKey($stadiumStand->id)->lockForUpdate()->firstOrFail();
            $stadium = $lockedStand->stadium()->firstOrFail();

            if (StadiumStandConstruction::query()->where('stadium_stand_id', $lockedStand->id)->whereNull('completed_at')->exists()) {
                throw new DomainException('This stadium stand already has construction in progress.');
            }

            $capacityIncrease = $targetCapacity - (int) $lockedStand->capacity;
            if ($capacityIncrease <= 0) {
                throw new DomainException('The new stadium stand capacity must be greater than its current capacity.');
            }

            $this->stadiumStandValidator->validatePosition($stadium, $lockedStand->position);
            $maximumCapacity = $this->stadiumRepository->maximumCapacityForStand($stadium->type, $lockedStand->position);
            $this->stadiumStandValidator->validateCapacityValue($targetCapacity, $maximumCapacity);

            $lockedStand->forceFill(['capacity' => $targetCapacity, 'status' => StadiumStandStatus::UNDER_CONSTRUCTION])->save();
            $construction = StadiumStandConstruction::query()->create([
                'instance_id' => $stadium->instance_id,
                'stadium_id' => $stadium->id,
                'stadium_stand_id' => $lockedStand->id,
                'target_capacity' => $targetCapacity,
                'capacity_increase' => $capacityIncrease,
                'started_at' => $startedAt,
                'completes_at' => $startedAt->addWeeks($this->durationInWeeks($capacityIncrease)),
            ]);

            $this->recalculateCapacities($stadium->fresh());

            return $construction;
        });
    }

    public function completeStandConstructionForInstance(Instance $instance, CarbonImmutable $asOf): int
    {
        $completed = 0;
        $affectedStadiumIds = [];

        StadiumStandConstruction::query()->where('instance_id', $instance->id)->whereNull('completed_at')
            ->whereDate('completes_at', '<=', $asOf->toDateString())->get()
            ->each(function (StadiumStandConstruction $construction) use (&$completed, &$affectedStadiumIds, $asOf): void {
                DB::transaction(function () use ($construction, &$completed, &$affectedStadiumIds, $asOf): void {
                    $lockedConstruction = StadiumStandConstruction::query()->whereKey($construction->id)->lockForUpdate()->first();
                    if ($lockedConstruction === null) {
                        return;
                    }

                    $affectedStadiumIds[$lockedConstruction->stadium_id] = true;
                    $stand = StadiumStand::query()->whereKey($lockedConstruction->stadium_stand_id)->first();
                    if ($stand !== null) {
                        $stand->forceFill(['status' => StadiumStandStatus::ACTIVE])->saveQuietly();
                    }

                    $lockedConstruction->update(['completed_at' => $asOf->toDateString()]);
                    $completed++;
                });
            });

        foreach (array_keys($affectedStadiumIds) as $stadiumId) {
            $stadium = $this->stadiumRepository->stadiumById((int) $stadiumId);
            if ($stadium !== null) {
                $this->recalculateCapacities($stadium);
            }
        }

        return $completed;
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

    private function constructionCost(Stadium $stadium, StadiumConstructionType $buildingType, ?int $standId, ?int $targetCapacity, ?int $categoryId, ?CommercialVenueSize $size): int
    {
        if ($buildingType === StadiumConstructionType::STAND) {
            if ($standId === null || $targetCapacity === null) {
                throw new DomainException('Stand construction details are required.');
            }

            $stand = $this->stadiumRepository->standForStadium($stadium, $standId);

            return $this->stadiumExpansionCost($stadium, $targetCapacity - (int) $stand->capacity);
        }

        if ($categoryId === null || $size === null) {
            throw new DomainException('Commercial venue construction details are required.');
        }

        return $this->venueConstructionCostCalculator->calculate($stadium, $this->stadiumRepository->findCategoryOrFail($categoryId), $size);
    }
}
