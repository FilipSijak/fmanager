<?php

namespace App\Services\ContractService\Domain;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

class InitialPlayerContract
{
    /** @param array<string, int|float> $terms
     * @return array<string, int|float|string>
     */
    public function data(array $terms, string $date, int $months): array
    {
        if ($months < PlayerContractRenewal::MIN_MONTHS || $months > PlayerContractRenewal::MAX_MONTHS) {
            throw new InvalidArgumentException('Initial contracts must last between 6 and 60 months.');
        }
        $startedAt = CarbonImmutable::parse($date);
        $data = ['contract_start' => $startedAt->toDateString(),
            'contract_end' => $startedAt->addMonthsNoOverflow($months)->toDateString(),
            'signing_fee' => 0, 'pc_promotion_salary_raise' => $terms['salary_raise'],
            'pc_demotion_salary_cut' => $terms['demotion']];
        foreach (PlayerContractRenewal::MONEY_FIELDS as $field) {
            $data[$field] = $terms[$field];
        }

        return $data;
    }
}
