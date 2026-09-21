<?php

namespace App\Services\StadiumService\Domain;

use App\Models\Stadium;
use App\Services\StadiumService\Operations\DemolishCommercialVenue;

class StadiumCommercialOperations
{
    public function __construct(
        private readonly DemolishCommercialVenue $demolishCommercialVenue,
    ) {}

    public function demolish(Stadium $stadium, int $venueId): int
    {
        return $this->demolishCommercialVenue->handle($stadium, $venueId);
    }
}
