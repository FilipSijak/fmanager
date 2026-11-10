<?php

namespace App\Services\ContractService\Domain;

use Carbon\CarbonImmutable;

class PlayerContractTerms
{
    public function __construct(private readonly PlayerSalaryExpectation $salaryExpectation = new PlayerSalaryExpectation) {}

    /** @param array<string, mixed> $offer
     * @return array<string, int|float|string>
     */
    public function agreed(array $offer, string $date, ?string $currentStart = null, ?string $currentEnd = null): array
    {
        $startedAt = CarbonImmutable::parse($date);
        $data = [
            'contract_start' => $currentStart ?? $startedAt->toDateString(),
            'contract_end' => $currentEnd ?? $startedAt->addYearsNoOverflow(3)->toDateString(),
            'signing_fee' => (int) ($offer['transfer_fee'] ?? 0),
            'pc_promotion_salary_raise' => (float) ($offer['pc_promotion_salary_raise'] ?? 0),
            'pc_demotion_salary_cut' => (float) ($offer['pc_demotion_salary_cut'] ?? 0),
        ];
        foreach (PlayerContractRenewal::MONEY_FIELDS as $field) {
            $data[$field] = (int) ($offer[$field] ?? 0);
        }

        return $data;
    }

    /** @return array<string, int|float> */
    public function forPlayer(int $ability, int $marketingRank, string $position, bool $hasContract, int $age = 26, ?int $potential = null): array
    {
        $ability = min(200, max(1, $ability));
        $tier = (int) ceil($ability / 10);
        $factor = $tier / 10;
        $salary = $this->salaryExpectation->salary($ability, $marketingRank, $age, $potential);
        $appearance = (int) round($ability * $factor * 50);

        return [
            'transfer_fee' => (int) round($salary * ((23 - $tier) / 10) * ($hasContract ? 1 : 5)),
            'salary' => $salary,
            'appearance' => $appearance,
            'clean_sheet' => $position === 'GK' ? $appearance : 0,
            'goal' => $appearance,
            'assist' => $appearance,
            'league' => $salary * 4,
            'promotion' => $salary * 4,
            'cup' => $salary * 2,
            'el' => $salary * 4,
            'cl' => $salary * 6,
            'salary_raise' => 0.02,
            'demotion' => 0.2,
        ];
    }
}
