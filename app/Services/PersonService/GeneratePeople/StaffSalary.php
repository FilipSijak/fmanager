<?php

namespace App\Services\PersonService\GeneratePeople;

class StaffSalary
{
    private const LOW_POTENTIAL_LIMIT = 50;

    private const MAXIMUM_POTENTIAL = 200;

    private const MINIMUM_SALARY = 500;

    private const MAXIMUM_SALARY = 12000;

    public function forPotential(int $potential): int
    {
        if ($potential <= self::LOW_POTENTIAL_LIMIT) {
            return self::MINIMUM_SALARY;
        }

        if ($potential >= self::MAXIMUM_POTENTIAL) {
            return self::MAXIMUM_SALARY;
        }

        $salaryRange = self::MAXIMUM_SALARY - self::MINIMUM_SALARY;
        $potentialRange = self::MAXIMUM_POTENTIAL - self::LOW_POTENTIAL_LIMIT;
        $salary = self::MINIMUM_SALARY
            + (($potential - self::LOW_POTENTIAL_LIMIT) / $potentialRange) * $salaryRange;

        return (int) (round($salary / 100) * 100);
    }
}
