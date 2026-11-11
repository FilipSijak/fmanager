<?php

namespace App\Services\ContractService\Domain;

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
    public function consider(PlayerContractContext $context, PlayerContract $contract, array $terms, int $months): array
    {
        if (! $context->isWillingToRenew()) {
            return ['status' => 'rejected', 'message' => 'The player wants to play for a more ambitious club and will not renew.'];
        }
        if ($terms['salary'] < (int) $contract->salary / 2) {
            return ['status' => 'rejected', 'message' => 'The player rejected the proposed salary reduction.'];
        }
        $requiredSalary = $this->requiredSalary($context, $contract->getAttributes(), $terms, $months);
        if ($terms['salary'] < $requiredSalary) {
            return ['status' => 'counter', 'message' => 'The player requests a weekly salary of £'.number_format($requiredSalary).' with these bonuses.', 'counter_salary' => $requiredSalary];
        }

        return ['status' => 'accepted', 'message' => 'The player accepted your renewal offer.'];
    }

    /** @param array<string, mixed> $current
     * @param  array<string, int>  $terms
     */
    public function requiredSalary(PlayerContractContext $context, array $current, array $terms, int $months): int
    {
        $currentSalary = (int) $current['salary'];
        $currentBonuses = $context->performance->weeklyBonuses(array_map(intval(...), $current));
        $salaryAnchor = ($currentSalary + min($currentSalary * 0.25, $currentBonuses)) * 0.95;
        $expectation = max($salaryAnchor, $context->marketSalary * $context->salaryAdjustment($months));
        $weeklySigningFee = ($terms['signing_fee'] ?? 0) / ($months * 52 / 12);
        $bonusCredit = min($expectation * 0.25, $context->performance->weeklyBonuses($terms) + $weeklySigningFee);

        return max(1, (int) ceil(round($expectation - $bonusCredit, 8)));
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
