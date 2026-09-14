<?php

namespace App\Services\StadiumService;

use App\Models\BaseData\BaseStadiumExpansionCost;
use App\Models\Country;
use App\Models\Stadium;
use App\Services\CommercialService\CommercialRankingConfig;
use DomainException;

class StadiumExpansionCostCalculator
{
    public function calculate(Stadium $stadium, int $additionalCapacity): int
    {
        if ($additionalCapacity <= 0) {
            throw new DomainException('Stadium expansion capacity must be greater than zero.');
        }

        $targetCapacity = $stadium->capacity + $additionalCapacity;

        if ($targetCapacity > $stadium->type->maximumCapacity()) {
            throw new DomainException('Stadium expansion exceeds the maximum capacity for its type.');
        }

        $country = Country::query()->where('code', $stadium->country_code)->first();

        if ($country === null || $country->ranking === null) {
            throw new DomainException('The stadium country does not have a ranking for stadium expansion.');
        }

        $costsPerThousandSeats = BaseStadiumExpansionCost::query()->pluck('cost_per_1000_seats', 'stadium_type');
        $remainingSeats = $additionalCapacity;
        $currentCapacity = (int) $stadium->capacity;
        $baseCost = 0.0;

        while ($remainingSeats > 0) {
            $type = StadiumType::fromCapacity($currentCapacity + 1);
            $costPerThousandSeats = $costsPerThousandSeats->get($type->value);

            if ($costPerThousandSeats === null) {
                throw new DomainException('No stadium expansion cost is configured for this stadium type.');
            }

            $seatsInTier = min($remainingSeats, $type->maximumCapacity() - $currentCapacity);
            $baseCost += ((int) $costPerThousandSeats * $seatsInTier) / 1000;
            $currentCapacity += $seatsInTier;
            $remainingSeats -= $seatsInTier;
        }

        return (int) round($baseCost * CommercialRankingConfig::countryCostMultiplier((int) $country->ranking));
    }
}
