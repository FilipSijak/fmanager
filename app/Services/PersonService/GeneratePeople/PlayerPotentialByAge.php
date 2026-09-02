<?php

namespace App\Services\PersonService\GeneratePeople;

use Carbon\CarbonInterface;

class PlayerPotentialByAge
{
    private const AGE_POTENTIAL_BRACKETS = [
        16 => 0.85,
        18 => 0.90,
        21 => 0.95,
        24 => 1.00,
        29 => 0.98,
        30 => 0.95,
        32 => 0.92,
        33 => 0.89,
        35 => 0.83,
        38 => 0.75,
        41 => 0.67,
    ];

    public function calculate(int $maxPotential, int $age): float
    {
        $multiplier = self::AGE_POTENTIAL_BRACKETS[16];

        foreach (self::AGE_POTENTIAL_BRACKETS as $minimumAge => $ageMultiplier) {
            if ($age < $minimumAge) {
                break;
            }

            $multiplier = $ageMultiplier;
        }

        return $maxPotential * $multiplier;
    }

    public function calculateOnDate(
        int $maxPotential,
        CarbonInterface $dateOfBirth,
        CarbonInterface $asOfDate
    ): float {
        return $this->calculate($maxPotential, $dateOfBirth->diffInYears($asOfDate));
    }
}
