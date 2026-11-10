<?php

namespace App\Services\ContractService\Domain;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class StaffContractTerms
{
    public function __construct(private readonly StaffSalary $salary) {}

    /** @return array{contract_start: string, contract_end: string, salary: int, signing_fee: null} */
    public function data(string $role, int $potential, string $date, int $months): array
    {
        if ($months < PlayerContractRenewal::MIN_MONTHS || $months > PlayerContractRenewal::MAX_MONTHS) {
            throw new InvalidArgumentException('Staff contracts must last between 6 and 60 months.');
        }
        $startedAt = CarbonImmutable::parse($date);

        return [
            'contract_start' => $startedAt->toDateString(),
            'contract_end' => $startedAt->addMonthsNoOverflow($months)->toDateString(),
            'salary' => $this->salary->estimatedSalaryForStaffRole($role, $potential),
            'signing_fee' => null,
        ];
    }
}
