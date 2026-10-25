<?php

namespace App\Services\MatchEngineService;

use App\Services\MatchEngineService\Data\MatchPlayer;

/**
 * Rates a side from the players in its lineup slots, on the 1-20 player attribute scale. Used at kick-off and again
 * whenever the players on the pitch change.
 */
class TeamRatings
{
    /** Rating given to a lineup slot nobody fills, such as a sent-off player's. */
    public const EMPTY_SLOT_RATING = 1.0;

    /**
     * How much each line contributes to a team rating, keyed by rating then line.
     *
     * @var array<string, array<string, float>>
     */
    private const LINE_WEIGHTS = [
        'shooting' => ['attack' => 1.0, 'midfield' => 0.5, 'defence' => 0.15],
        'playmaking' => ['midfield' => 1.0, 'attack' => 0.3, 'defence' => 0.3],
        'defending' => ['defence' => 1.0, 'midfield' => 0.5, 'attack' => 0.15],
    ];

    /**
     * How much a player's creating ability counts towards the team's chances by the position they play: midfield counts
     * the most, then forwards, wing backs and finally centre backs.
     */
    private const CHANCE_CREATION_WEIGHTS = [
        'midfield' => 1.0,
        'forward' => 0.6,
        'wing_back' => 0.35,
        'centre_back' => 0.1,
        'goalkeeper' => 0.0,
    ];

    /**
     * @param  list<array{position: string, player: ?MatchPlayer}>  $slots
     * @return array{attack: float, midfield: float, defence: float, goalkeeping: float, chanceCreation: float}
     */
    public function calculate(array $slots): array
    {
        return [
            'attack' => $this->lineWeightedRating($slots, 'shooting'),
            'midfield' => $this->lineWeightedRating($slots, 'playmaking'),
            'defence' => $this->lineWeightedRating($slots, 'defending'),
            'goalkeeping' => $this->goalkeeperRating($slots),
            'chanceCreation' => $this->chanceCreationRating($slots),
        ];
    }

    /** @param list<array{position: string, player: ?MatchPlayer}> $slots */
    private function lineWeightedRating(array $slots, string $skill): float
    {
        $weightedRating = 0.0;
        $totalWeight = 0.0;

        foreach ($slots as $slot) {
            $weight = self::LINE_WEIGHTS[$skill][PitchLine::fromPosition($slot['position'])->value] ?? 0.0;
            $weightedRating += $weight * ($slot['player']?->{$skill} ?? self::EMPTY_SLOT_RATING);
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $weightedRating / $totalWeight : self::EMPTY_SLOT_RATING;
    }

    /** @param list<array{position: string, player: ?MatchPlayer}> $slots */
    private function goalkeeperRating(array $slots): float
    {
        foreach ($slots as $slot) {
            if (PitchLine::fromPosition($slot['position']) === PitchLine::GOALKEEPER) {
                return $slot['player']?->goalkeeping ?? self::EMPTY_SLOT_RATING;
            }
        }

        return self::EMPTY_SLOT_RATING;
    }

    /**
     * Chance creation averaged by the weight of the position each player plays. It is an average rather than a total so
     * every formation creates the same number of chances with average players; the formation's style is left to its
     * tactical tendency.
     *
     * @param  list<array{position: string, player: ?MatchPlayer}>  $slots
     */
    private function chanceCreationRating(array $slots): float
    {
        $weightedRating = 0.0;
        $totalWeight = 0.0;

        foreach ($slots as $slot) {
            $weight = self::CHANCE_CREATION_WEIGHTS[PositionRole::fromPosition($slot['position'])->value];
            $weightedRating += $weight * ($slot['player']?->creating ?? self::EMPTY_SLOT_RATING);
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? $weightedRating / $totalWeight : self::EMPTY_SLOT_RATING;
    }
}
