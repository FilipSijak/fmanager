<?php

namespace App\Services\FinanceService\Domain;

use App\Services\CommercialService\CommercialRankingConfig;

class SponsorshipCalculator
{
    public const int MAX_ANNUAL_AMOUNT = 30_000_000;

    public function annualAmount(int $clubRank): int
    {
        return (int) round(self::MAX_ANNUAL_AMOUNT * CommercialRankingConfig::normalizeClubRank($clubRank) ** 3);
    }
}
