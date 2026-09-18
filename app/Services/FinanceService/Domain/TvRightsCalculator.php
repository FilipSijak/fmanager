<?php

namespace App\Services\FinanceService\Domain;

use App\Services\CommercialService\CommercialRankingConfig;

final class TvRightsCalculator
{
    private const int MAX_TV_RIGHTS = 40_000_000;

    private const float COMPETITION_WEIGHT = 0.5;

    private const float CLUB_WEIGHT = 0.5;

    public function calculate(int $competitionRank, int $clubRank): int
    {
        $weightedRank = (CommercialRankingConfig::normalizeCompetitionRank($competitionRank) * self::COMPETITION_WEIGHT)
            + (CommercialRankingConfig::normalizeClubRank($clubRank) * self::CLUB_WEIGHT);

        return (int) round(self::MAX_TV_RIGHTS * $weightedRank);
    }
}
