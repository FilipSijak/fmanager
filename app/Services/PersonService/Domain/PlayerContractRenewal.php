<?php

namespace App\Services\PersonService\Domain;

use App\Models\PlayerContract;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class PlayerContractRenewal
{
    public const MIN_MONTHS = 6;

    public const MAX_MONTHS = 60;

    public const MONEY_FIELDS = ['salary', 'appearance', 'goal', 'assist', 'clean_sheet', 'league', 'promotion', 'cup', 'el', 'cl'];

    /** @param array<string, int> $terms
     * @return array{status: string, message: string, counter_salary?: int}
     */
    public function consider(int $ambition, int $clubRank, PlayerContract $contract, array $terms): array
    {
        if ($ambition - $clubRank > 4) {
            return ['status' => 'rejected', 'message' => 'The player wants to play for a more ambitious club and will not renew.'];
        }
        if ($terms['salary'] < (int) $contract->salary / 2) {
            return ['status' => 'rejected', 'message' => 'The player rejected the proposed salary reduction.'];
        }
        $currentPackage = (int) $contract->salary;
        $offeredBonuses = 0;
        foreach (['appearance', 'goal', 'assist', 'clean_sheet'] as $field) {
            $currentPackage += (int) $contract->{$field};
            $offeredBonuses += $terms[$field];
        }
        $requiredSalary = max(1, (int) ceil($currentPackage * (1 + max(0, $ambition - $clubRank) / 10)) - $offeredBonuses);
        if ($terms['salary'] < $requiredSalary) {
            return ['status' => 'counter', 'message' => 'The player requests a weekly salary of £'.number_format($requiredSalary).' with these bonuses.', 'counter_salary' => $requiredSalary];
        }

        return ['status' => 'accepted', 'message' => 'The player accepted your renewal offer.'];
    }

    public function endsAt(string $date, int $months, string $currentEnd): string
    {
        if ($months < self::MIN_MONTHS || $months > self::MAX_MONTHS) {
            throw ValidationException::withMessages(['months' => 'Renewals must last between 6 and 60 months.']);
        }
        $end = CarbonImmutable::parse($date)->addMonthsNoOverflow($months);
        if ($end->lte(CarbonImmutable::parse($currentEnd))) {
            throw ValidationException::withMessages(['months' => 'The new contract must end after the current contract.']);
        }

        return $end->toDateString();
    }

    /** @param array<string, mixed> $data
     * @return array<string, int>
     */
    public function terms(array $data): array
    {
        $terms = ['signing_fee' => (int) ($data['transfer_fee'] ?? 0)];
        foreach (self::MONEY_FIELDS as $field) {
            $terms[$field] = (int) ($data[$field] ?? 0);
        }

        return $terms;
    }
}
