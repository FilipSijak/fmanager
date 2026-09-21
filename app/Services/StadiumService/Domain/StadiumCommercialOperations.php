<?php

namespace App\Services\StadiumService\Domain;

use App\Models\Stadium;
use App\Models\StadiumCommercialVenue;
use App\Services\CommercialService\CommercialVenueSize;
use App\Services\StadiumService\Operations\BuildCommercialVenue;
use App\Services\StadiumService\Operations\DemolishCommercialVenue;

class StadiumCommercialOperations
{
    public function __construct(
        private readonly BuildCommercialVenue $buildCommercialVenue,
        private readonly DemolishCommercialVenue $demolishCommercialVenue,
    ) {}

    public function build(Stadium $stadium, int $categoryId, CommercialVenueSize $size): StadiumCommercialVenue
    {
        return $this->buildCommercialVenue->handle($stadium, $categoryId, $size);
    }

    public function demolish(Stadium $stadium, int $venueId): int
    {
        return $this->demolishCommercialVenue->handle($stadium, $venueId);
    }
}
