<?php

namespace App\Services\FinanceService\Domain;

use App\Services\CommercialService\CommercialRankingConfig;
use InvalidArgumentException;

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

    public function calculateForCompetition(
        int $competitionRank,
        int $clubRank,
        string $competitionType,
        int $clubsNumber,
        ?int $groups,
        int $gamesPlayed,
    ): int {
        if ($gamesPlayed < 0) {
            throw new InvalidArgumentException('Games played cannot be negative.');
        }

        if ($competitionType === 'league') {
            return $this->calculate($competitionRank, $clubRank);
        }

        $totalGames = $this->totalGamesForClub($competitionType, $clubsNumber, $groups);
        $participationRate = min(1.0, $gamesPlayed / $totalGames);

        return (int) round($this->calculate($competitionRank, $clubRank) * $participationRate);
    }

    public function totalGamesForClub(string $competitionType, int $clubsNumber, ?int $groups): int
    {
        if ($clubsNumber < 2) {
            throw new InvalidArgumentException('A competition must have at least two clubs.');
        }

        return match ($competitionType) {
            'league' => 2 * ($clubsNumber - 1),
            'tournament' => $this->totalTournamentGames($clubsNumber, $groups),
            default => throw new InvalidArgumentException('Unsupported competition type.'),
        };
    }

    private function totalTournamentGames(int $clubsNumber, ?int $groups): int
    {
        if ($groups !== 1) {
            return $this->knockoutGamesForClub($clubsNumber);
        }

        if ($clubsNumber % 4 !== 0) {
            throw new InvalidArgumentException('Group tournaments must have a multiple of four clubs.');
        }

        $groupCount = intdiv($clubsNumber, 4);
        $knockoutParticipants = $groupCount * 2;

        return 6 + $this->knockoutGamesForClub($knockoutParticipants);
    }

    private function knockoutGamesForClub(int $participants): int
    {
        if ($participants < 2 || ($participants & ($participants - 1)) !== 0) {
            throw new InvalidArgumentException('Knockout tournaments require a power-of-two number of clubs.');
        }

        return (2 * ((int) log($participants, 2) - 1)) + 1;
    }
}
