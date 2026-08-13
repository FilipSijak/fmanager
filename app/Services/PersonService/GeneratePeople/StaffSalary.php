<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Services\PersonService\PersonConfig\PersonTypes;
use InvalidArgumentException;

class StaffSalary
{
    private const LOW_POTENTIAL_LIMIT = 50;

    private const MAXIMUM_POTENTIAL = 200;

    private const TOP_SALARY_POTENTIAL = self::MAXIMUM_POTENTIAL - 10;

    public function estimatedSalaryForStaffRole(string $role, int $potential): int
    {
        [$minimumSalary, $maximumSalary] = $this->salaryRange($role);

        if ($potential <= self::LOW_POTENTIAL_LIMIT) {
            return $minimumSalary;
        }

        if ($potential >= self::TOP_SALARY_POTENTIAL) {
            return $maximumSalary;
        }

        $salaryRange = $maximumSalary - $minimumSalary;
        $potentialRange = self::TOP_SALARY_POTENTIAL - self::LOW_POTENTIAL_LIMIT;
        $salary = $minimumSalary
            + (($potential - self::LOW_POTENTIAL_LIMIT) / $potentialRange) * $salaryRange;

        return (int) (round($salary / 100) * 100);
    }

    /** @return array{int, int} */
    private function salaryRange(string $role): array
    {
        return match ($role) {
            PersonTypes::PHYSIO, PersonTypes::YOUTH_PHYSIO => [500, 3000],
            PersonTypes::SCOUT => [1000, 2000],
            PersonTypes::COACH, PersonTypes::YOUTH_COACH => [1000, 8000],
            PersonTypes::ASSISTANT_MANAGER => [1500, 10000],
            PersonTypes::MANAGER => [5000, 10000000],
            default => throw new InvalidArgumentException("Unsupported staff role: {$role}"),
        };
    }
}
