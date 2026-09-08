<?php

namespace App\Domain\PlayerDevelopment;

use InvalidArgumentException;

class AgePotentialCurve
{
    /**
     * @param  array<int, float>  $brackets
     */
    public function __construct(private readonly array $brackets)
    {
        $this->validateBrackets($brackets);
    }

    public function multiplierFor(int $age): float
    {
        $multiplier = $this->brackets[array_key_first($this->brackets)];

        foreach ($this->brackets as $minimumAge => $ageMultiplier) {
            if ($age < $minimumAge) {
                break;
            }

            $multiplier = $ageMultiplier;
        }

        return $multiplier;
    }

    public function potentialFor(int $maxPotential, int $age): float
    {
        return $maxPotential * $this->multiplierFor($age);
    }

    /**
     * @param  array<int, float>  $brackets
     */
    private function validateBrackets(array $brackets): void
    {
        if ($brackets === [] || array_key_first($brackets) < 0) {
            throw new InvalidArgumentException('Age potential curves must have a non-negative starting age.');
        }

        $previousAge = null;

        foreach ($brackets as $age => $multiplier) {
            if (! is_int($age) || ($previousAge !== null && $age <= $previousAge)) {
                throw new InvalidArgumentException('Age potential curve ages must be strictly increasing integers.');
            }

            if ($multiplier < 0 || $multiplier > 1) {
                throw new InvalidArgumentException('Age potential curve multipliers must be between 0 and 1.');
            }

            $previousAge = $age;
        }
    }
}
