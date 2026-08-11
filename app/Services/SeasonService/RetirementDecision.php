<?php

namespace App\Services\SeasonService;

use App\Models\Player;
use Carbon\CarbonImmutable;
use Closure;

class RetirementDecision
{
    private const MINIMUM_RETIREMENT_AGE = 32;

    private const GUARANTEED_RETIREMENT_AGE = 45;

    private const MAXIMUM_CHANCE_BASIS_POINTS = 10000;

    private Closure $randomRoll;

    public function __construct(?Closure $randomRoll = null)
    {
        $this->randomRoll = $randomRoll
            ?? static fn (): int => random_int(1, self::MAXIMUM_CHANCE_BASIS_POINTS);
    }

    public function shouldRetire(Player $player, CarbonImmutable $asOfDate): bool
    {
        $age = CarbonImmutable::parse($player->dob)->diffInYears($asOfDate);
        $chance = $this->ageChanceBasisPoints($age);

        return $chance > 0 && ($this->randomRoll)() <= $chance;
    }

    public function ageChanceBasisPoints(int $age): int
    {
        if ($age < self::MINIMUM_RETIREMENT_AGE) {
            return 0;
        }

        if ($age >= self::GUARANTEED_RETIREMENT_AGE) {
            return self::MAXIMUM_CHANCE_BASIS_POINTS;
        }

        return (int) round(
            (($age - self::MINIMUM_RETIREMENT_AGE + 1)
                / (self::GUARANTEED_RETIREMENT_AGE - self::MINIMUM_RETIREMENT_AGE + 1))
            * self::MAXIMUM_CHANCE_BASIS_POINTS
        );
    }
}
