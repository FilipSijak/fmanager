<?php

namespace App\Services\MatchEngineService;

/**
 * A player's 3-10 match rating, starting from 6 and moved by their contributions and their team's result.
 */
class PlayerMatchRating
{
    private const BASE_RATING = 6.0;

    /** Pass completion an average passer reaches; better completion lifts the rating, worse lowers it. */
    private const AVERAGE_PASS_COMPLETION = 0.8;

    /** Minutes in goal a goalkeeper needs without conceding to keep a clean sheet. */
    public const CLEAN_SHEET_MINUTES = 60;

    /**
     * @param  array<string, int|bool|string|null>  $stats
     * @param  int  $result  1 for a win, 0 for a draw, -1 for a defeat.
     */
    public function rate(array $stats, bool $cleanSheet, int $result): float
    {
        $passCompletionBonus = $stats['passes_attempted'] >= 5
            ? ($stats['passes_completed'] / $stats['passes_attempted'] - self::AVERAGE_PASS_COMPLETION) * 3
            : 0.0;

        $rating = self::BASE_RATING
            + $stats['goals'] * 1.0
            + $stats['assists'] * 0.7
            + $stats['key_passes'] * 0.15
            + $stats['shots_on_target'] * 0.1
            - ($stats['shots'] - $stats['shots_on_target']) * 0.05
            + $stats['tackles_won'] * 0.12
            + $stats['interceptions'] * 0.1
            - $stats['fouls_committed'] * 0.05
            - $stats['yellow_cards'] * 0.3
            - $stats['red_cards'] * 1.5
            + $stats['saves'] * 0.25
            - $stats['goals_conceded'] * 0.35
            + ($cleanSheet ? 0.8 : 0.0)
            + $passCompletionBonus
            + $result * 0.3;

        return round(max(3.0, min(10.0, $rating)), 1);
    }
}
