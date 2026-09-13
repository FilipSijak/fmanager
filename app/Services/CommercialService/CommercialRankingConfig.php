<?php

namespace App\Services\CommercialService;

final class CommercialRankingConfig
{
    private const int MAX_COUNTRY_RANK = 100;

    private const int MAX_COMPETITION_RANK = 10000;

    private const int MAX_CLUB_RANK = 20;

    public static function normalizeCountryRank(int $rank): float
    {
        return self::normalize($rank, self::MAX_COUNTRY_RANK);
    }

    public static function normalizeCompetitionRank(int $rank): float
    {
        return self::normalize($rank, self::MAX_COMPETITION_RANK);
    }

    public static function normalizeClubRank(int $rank): float
    {
        return self::normalize($rank, self::MAX_CLUB_RANK);
    }

    private static function normalize(int $rank, int $maximumRank): float
    {
        return max(0.0, min(1.0, $rank / $maximumRank));
    }
}
