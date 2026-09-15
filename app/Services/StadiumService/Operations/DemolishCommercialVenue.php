<?php

namespace App\Services\StadiumService\Operations;

use App\Models\Stadium;
use App\Repositories\StadiumRepository;
use App\Services\CommercialService\VenueConstructionCostCalculator;
use DomainException;
use Illuminate\Support\Facades\DB;

class DemolishCommercialVenue
{
    public function __construct(
        private readonly StadiumRepository $stadiumRepository,
        private readonly VenueConstructionCostCalculator $venueConstructionCostCalculator,
    ) {}

    public function handle(Stadium $stadium, int $venueId): int
    {
        return DB::transaction(function () use ($stadium, $venueId): int {
            $lockedStadium = $this->stadiumRepository->lockStadium($stadium->id);
            $venue = $this->stadiumRepository->commercialVenueForStadium($lockedStadium, $venueId);

            if ($venue === null) {
                throw new DomainException('This commercial venue does not exist at the stadium.');
            }

            $buildCost = $venue->build_cost ?? $this->venueConstructionCostCalculator->calculate(
                $lockedStadium,
                $venue->category,
                $venue->size,
            );
            $demolitionCost = $this->venueConstructionCostCalculator->demolitionCost($buildCost);

            $venue->delete();

            return $demolitionCost;
        });
    }
}
