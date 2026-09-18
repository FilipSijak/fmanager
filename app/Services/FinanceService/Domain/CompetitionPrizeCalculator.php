<?php

namespace App\Services\FinanceService\Domain;

use App\Services\CommercialService\CommercialRankingConfig;

final class CompetitionPrizeCalculator
{
    private const int MAX_LEAGUE_PRIZE = 20_000_000;

    public function calculateLeaguePrize(int $competitionRank): int
    {
        return (int) round(self::MAX_LEAGUE_PRIZE * CommercialRankingConfig::normalizeCompetitionRank($competitionRank));
    }
}
