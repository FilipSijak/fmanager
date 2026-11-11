<?php

namespace App\Services\ContractService\Domain;

use App\Services\PersonService\PersonConfig\Player\PlayerFields;

class PlayerSalaryExpectation
{
    /** @param array<string, mixed> $attributes */
    public function ability(array $attributes, string $position): int
    {
        $technical = $this->average($attributes, $position === 'GK' ? PlayerFields::GOALKEEPING_FIELDS : PlayerFields::TECHNICAL_FIELDS);
        $mental = $this->average($attributes, PlayerFields::MENTAL_FIELDS);
        $physical = $this->average($attributes, PlayerFields::PHYSICAL_FIELDS);

        return (int) round(($technical * 0.4 + $mental * 0.3 + $physical * 0.3) * 10);
    }

    public function salary(int $ability, int $marketingRank, int $age = 26, ?int $potential = null): int
    {
        $ability = min(200, max(1, $ability));
        $reputation = min(200, max(1, $marketingRank > 0 ? $marketingRank : $ability));
        $tier = (int) ceil($ability / 10);
        $ageAdjustment = 1 - min(0.2, max(0, $age - 30) * 0.03);
        $potentialAdjustment = $age < 24 ? 1 + min(0.15, max(0, ($potential ?? $ability) - $ability) / 400) : 1;

        return max(1, (int) round($ability * ($tier / 10) * 1000 * ($reputation / 100) / 3 * $ageAdjustment * $potentialAdjustment));
    }

    /** @param array<string, mixed> $attributes
     * @param  list<string>  $fields
     */
    private function average(array $attributes, array $fields): float
    {
        $total = 0;
        foreach ($fields as $field) {
            $total += min(20, max(1, (float) ($attributes[$field] ?? 1)));
        }

        return $total / count($fields);
    }
}
