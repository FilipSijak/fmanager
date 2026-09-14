<?php

namespace App\Services\CommercialService;

use App\Models\BaseData\BaseCommercialCategory;
use App\Models\Country;
use App\Models\Stadium;
use DomainException;

class VenueConstructionCostCalculator
{
    private const float DEMOLITION_COST_RATE = 0.10;

    private const int DEMOLITION_COST_ROUNDING_UNIT = 1000;

    public function calculate(
        Stadium $stadium,
        BaseCommercialCategory $category,
        CommercialVenueSize $size,
    ): int {
        $baseCost = $category->venueCosts()
            ->where('size', $size->value)
            ->value('base_cost');

        if ($baseCost === null) {
            throw new DomainException('No construction cost is configured for this commercial venue size.');
        }

        $country = Country::query()
            ->where('code', $stadium->country_code)
            ->first();

        if ($country === null || $country->ranking === null) {
            throw new DomainException('The stadium country does not have a ranking for venue construction.');
        }

        $countryMultiplier = 1 + (
            CommercialRankingConfig::normalizeCountryRank((int) $country->ranking)
            * CommercialRankingConfig::MAX_COUNTRY_COST_PREMIUM
        );

        return (int) round($baseCost * $countryMultiplier);
    }

    public function demolitionCost(int $buildCost): int
    {
        return (int) round(($buildCost * self::DEMOLITION_COST_RATE) / self::DEMOLITION_COST_ROUNDING_UNIT)
            * self::DEMOLITION_COST_ROUNDING_UNIT;
    }
}
