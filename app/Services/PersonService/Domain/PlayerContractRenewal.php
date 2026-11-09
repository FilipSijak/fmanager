<?php

namespace App\Services\PersonService\Domain;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class PlayerContractRenewal
{
    public const MIN_MONTHS = 6;

    public const MAX_MONTHS = 60;

    public const MONEY_FIELDS = ['salary', 'appearance', 'goal', 'assist', 'clean_sheet', 'league', 'promotion', 'cup', 'el', 'cl'];

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
