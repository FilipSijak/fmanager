<?php

namespace App\Services\SeasonService;

use App\Models\Instance;
use App\Repositories\PlayerRepository;
use Carbon\CarbonImmutable;
use Closure;

class PlayerRetirement
{
    private const MINIMUM_RETIREMENT_AGE = 32;

    private const GUARANTEED_RETIREMENT_AGE = 45;

    private const MAXIMUM_CHANCE_BASIS_POINTS = 10000;

    private Closure $randomRoll;

    public function __construct(
        private readonly PlayerRepository $playerRepository,
        ?Closure $randomRoll = null
    ) {
        $this->randomRoll = $randomRoll
            ?? static fn (): int => random_int(1, self::MAXIMUM_CHANCE_BASIS_POINTS);
    }

    public function retireEligiblePlayers(Instance $instance): int
    {
        $asOfDate = CarbonImmutable::parse($instance->instance_date)->startOfDay();
        $cutoffDate = $asOfDate->subYears(self::MINIMUM_RETIREMENT_AGE);
        $retiredCount = 0;

        $players = $this->playerRepository->playersEligibleForRetirement(
            (int) $instance->id,
            $cutoffDate->toDateString()
        );

        foreach ($players as $player) {
            $age = CarbonImmutable::parse($player->dob)->diffInYears($asOfDate);

            if (! $this->shouldRetire($age)) {
                continue;
            }

            if ($this->playerRepository->retirePlayer((int) $player->id)) {
                $retiredCount++;
            }
        }

        return $retiredCount;
    }

    public function retirementChanceBasisPoints(int $age): int
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

    private function shouldRetire(int $age): bool
    {
        $chance = $this->retirementChanceBasisPoints($age);

        return $chance > 0 && ($this->randomRoll)() <= $chance;
    }
}
