<?php

namespace App\Services\FinanceService\Domain;

class SponsorshipOfferCalculator
{
    public const int MAX_RANK_DIFFERENCE = 3;

    public function __construct(private readonly SponsorshipCalculator $sponsorships) {}

    public function isEligible(int $clubRank, int $sponsorRank): bool
    {
        return $clubRank > 0 && $sponsorRank > 0 && abs($clubRank - $sponsorRank) <= self::MAX_RANK_DIFFERENCE;
    }

    public function offerRank(int $clubRank, int $sponsorRank): int
    {
        return (int) floor(($clubRank + $sponsorRank) / 2);
    }

    public function initialAmount(int $clubRank, int $sponsorRank): int
    {
        return $this->sponsorships->annualAmount($this->offerRank($clubRank, $sponsorRank));
    }

    public function maximumAmount(int $clubRank, int $sponsorRank): int
    {
        return min(SponsorshipCalculator::MAX_ANNUAL_AMOUNT, (int) round($this->initialAmount($clubRank, $sponsorRank) * 1.1));
    }

    public function counterAmount(int $currentAmount, int $maximumAmount): int
    {
        return min($maximumAmount, (int) ceil($currentAmount * 1.05));
    }
}
