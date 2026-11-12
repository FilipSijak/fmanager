<?php

namespace App\Services\FinanceService\Domain;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PayrollCalculator
{
    public function payday(string $gameDate): ?CarbonImmutable
    {
        $date = CarbonImmutable::parse($gameDate)->startOfDay();

        return $date->isMonday() ? $date : null;
    }

    /**
     * @param  array{players: list<int>, staff: list<int>}  $salaries
     * @return array{player_wages: int, staff_wages: int}
     */
    public function wages(array $salaries): array
    {
        return [
            'player_wages' => array_sum($salaries['players']),
            'staff_wages' => array_sum($salaries['staff']),
        ];
    }

    /**
     * @param  array{player_wages: int, staff_wages: int}  $wages
     * @return list<array{amount: int, category: string, reference: string}>
     */
    public function payments(int $clubId, CarbonInterface $date, array $wages): array
    {
        $payments = [];
        foreach ($wages as $category => $amount) {
            if ($amount > 0) {
                $payments[] = [
                    'amount' => $amount,
                    'category' => $category,
                    'reference' => 'payroll:'.$clubId.':'.$date->toDateString().':'.$category,
                ];
            }
        }

        return $payments;
    }
}
