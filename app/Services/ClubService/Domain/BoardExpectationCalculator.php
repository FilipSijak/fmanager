<?php

namespace App\Services\ClubService\Domain;

class BoardExpectationCalculator
{
    /**
     * Each factor is normalized against league peers and contributes one quarter of the score.
     *
     * @param  array{budget: int, transfer_budget: int, team_quality: float, club_rank: int}  $club
     * @param  list<array{budget: int, transfer_budget: int, team_quality: float, club_rank: int}>  $peers
     */
    public function score(array $club, array $peers): int
    {
        $total = 0.0;

        foreach (array_keys($club) as $factor) {
            $values = array_column($peers, $factor);
            $minimum = min($values);
            $maximum = max($values);
            $total += $maximum === $minimum ? 50 : 100 * ($club[$factor] - $minimum) / ($maximum - $minimum);
        }

        return max(0, min(100, (int) round($total / count($club))));
    }

    public function targetPosition(int $rating, int $leagueSize): int
    {
        return max(1, min($leagueSize, (int) ceil($leagueSize * (100 - $rating) / 100)));
    }
}
