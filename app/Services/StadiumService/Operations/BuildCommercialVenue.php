<?php

namespace App\Services\StadiumService\Operations;

use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Repositories\StadiumRepository;
use App\Services\CommercialService\CommercialVenueSize;
use App\Services\CommercialService\VenueConstructionCostCalculator;
use DomainException;
use Illuminate\Support\Facades\DB;

class BuildCommercialVenue
{
    public function __construct(
        private readonly StadiumRepository $stadiumRepository,
        private readonly VenueConstructionCostCalculator $venueConstructionCostCalculator,
    ) {}

    public function handle(Stadium $stadium, int $categoryId, CommercialVenueSize $size): StadiumCommercialVenue
    {
        return DB::transaction(function () use ($stadium, $categoryId, $size): StadiumCommercialVenue {
            $lockedStadium = $this->stadiumRepository->lockStadium($stadium->id);
            $stadiumType = $lockedStadium->type;

            if (! $this->stadiumRepository->categoryIsAvailableForType($categoryId, $stadiumType)) {
                throw new DomainException('This commercial category is not available for the stadium type.');
            }

            if ($this->stadiumRepository->commercialVenueExists($lockedStadium, $categoryId)) {
                throw new DomainException('This commercial venue category has already been built at the stadium.');
            }

            if ($this->stadiumRepository->commercialVenueCount($lockedStadium) >= $lockedStadium->commercial_limit) {
                throw new DomainException('The stadium has reached its commercial venue limit.');
            }

            return $this->stadiumRepository->createCommercialVenue([
                'instance_id' => $lockedStadium->instance_id,
                'stadium_id' => $lockedStadium->id,
                'category_id' => $categoryId,
                'size' => $size,
                'build_cost' => $this->venueConstructionCostCalculator->calculate(
                    $lockedStadium,
                    $this->stadiumRepository->findCategoryOrFail($categoryId),
                    $size,
                ),
            ]);
        });
    }
}
