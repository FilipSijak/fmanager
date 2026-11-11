<?php

namespace App\Services\ContractService\Domain;

class PlayerContractPerformance
{
    /** @param list<array<string, mixed>> $appearances */
    public function __construct(public readonly int $games = 0, private readonly array $appearances = []) {}

    public function playingShare(): ?float
    {
        if ($this->games === 0) {
            return null;
        }

        return min(1, array_sum(array_column($this->appearances, 'minutes_played')) / ($this->games * 90));
    }

    public function formAdjustment(): float
    {
        if ($this->appearances === []) {
            return 0;
        }
        $rating = array_sum(array_column($this->appearances, 'rating')) / count($this->appearances);

        return max(-0.15, min(0.15, ($rating - 6.5) * 0.05)) * min(1, count($this->appearances) / 5);
    }

    /** @param array<string, int> $terms */
    public function weeklyBonuses(array $terms): float
    {
        $appearanceRate = $this->games === 0 ? 0.5 : min(1, count($this->appearances) / $this->games);
        $value = ($terms['appearance'] ?? 0) * $appearanceRate;
        foreach (['goal' => 'goals', 'assist' => 'assists', 'clean_sheet' => 'clean_sheet'] as $field => $stat) {
            $rate = $this->games === 0 ? 0 : array_sum(array_column($this->appearances, $stat)) / $this->games;
            $value += ($terms[$field] ?? 0) * min($field === 'clean_sheet' ? 1 : 2, $rate);
        }

        return $value * 38 / 52;
    }
}
