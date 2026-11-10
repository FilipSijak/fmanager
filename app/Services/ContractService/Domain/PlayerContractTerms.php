<?php

namespace App\Services\ContractService\Domain;

class PlayerContractTerms
{
    /** @return array<string, int|float> */
    public function forPlayer(int $potential, int $marketingRank, string $position, bool $hasContract): array
    {
        $potential = min(200, max(1, $potential));
        $tier = (int) ceil($potential / 10);
        $factor = $tier / 10;
        $salary = max(1, (int) round($potential * $factor * 1000 * ($marketingRank / 100) / 3));
        $appearance = (int) round($potential * $factor * 50);

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
