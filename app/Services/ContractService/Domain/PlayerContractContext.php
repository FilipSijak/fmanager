<?php

namespace App\Services\ContractService\Domain;

readonly class PlayerContractContext
{
    public function __construct(
        public int $marketSalary,
        public int $age,
        public int $ambition,
        public int $clubRank,
        public int $loyalty,
        public PlayerContractPerformance $performance = new PlayerContractPerformance,
    ) {}

    public function isWillingToRenew(): bool
    {
        $playingShare = $this->performance->playingShare();
        $playingTimeAllowance = $playingShare === null ? 0 : ($playingShare >= 0.7 ? 0.5 : ($playingShare < 0.25 ? -1 : 0));

        return $this->ambition - $this->clubRank <= 4 + max(-2.5, min(2.5, ($this->loyalty - 10) / 4)) + $playingTimeAllowance;
    }

    public function salaryAdjustment(int $months): float
    {
        $ambition = min(0.25, max(0, $this->ambition - $this->clubRank) * 0.025);
        $loyalty = max(-0.05, min(0.05, (10 - $this->loyalty) * 0.005));
        $share = $this->performance->playingShare();
        $playingTime = $share === null ? 0 : ($share - 0.5) * 0.1;
        $duration = $months < 12 ? 0.02 : 0;
        if ($months > 36) {
            $duration = $this->age < 24 ? ($months - 36) * 0.005 : ($this->age > 30 ? -($months - 36) / 300 : 0);
        }

        return 1 + $ambition + $loyalty + $playingTime + $this->performance->formAdjustment() + $duration;
    }
}
