<?php

namespace App\Services\FinanceService\Domain;

use App\Services\CommercialService\CommercialRankingConfig;
use InvalidArgumentException;

final class CompetitionPrizeCalculator
{
    private const int MAX_LEAGUE_PRIZE = 20_000_000;

    private const int MAX_ROUND_PRIZE = 4_000_000;

    private const int MAX_WIN_PRIZE = 2_000_000;

    private const int MAX_DRAW_PRIZE = 1_000_000;

    public function calculateLeaguePrize(int $competitionRank): int
    {
        return (int) round(self::MAX_LEAGUE_PRIZE * CommercialRankingConfig::normalizeCompetitionRank($competitionRank));
    }

    public function calculateContinentalPrize(
        int $competitionRank,
        int $roundsPlayed,
        int $wins,
        int $draws,
    ): int {
        if ($roundsPlayed < 0 || $wins < 0 || $draws < 0) {
            throw new InvalidArgumentException('Prize participation values cannot be negative.');
        }

        $rankMultiplier = CommercialRankingConfig::normalizeCompetitionRank($competitionRank);

        return (int) round($rankMultiplier * (
            ($roundsPlayed * self::MAX_ROUND_PRIZE)
            + ($wins * self::MAX_WIN_PRIZE)
            + ($draws * self::MAX_DRAW_PRIZE)
        ));
    }
}
