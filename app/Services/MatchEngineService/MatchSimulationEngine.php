<?php

namespace App\Services\MatchEngineService;

use App\Services\MatchEngineService\Data\MatchTeam;
use App\Services\TacticsService\FormationTendency;
use App\Services\TacticsService\Mentality;
use Random\Randomizer;

/**
 * Plays a match as two steps per side: how many chances it creates, then how many of them it converts.
 */
class MatchSimulationEngine
{
    public const ENGINE_VERSION = 3;

    /** Chances an average side creates against an equal opponent on neutral ground. */
    private const BASE_CHANCES = 10.0;

    /** Share of chances an average side converts against an average defence (10 chances x 0.13 = 1.3 goals). */
    private const BASE_CONVERSION = 0.13;

    private const HOME_ADVANTAGE = 1.12;

    private const AWAY_DISADVANTAGE = 0.92;

    /** Rating of an average player on the 1-20 attribute scale; sides rated here create and convert the base amount. */
    private const AVERAGE_RATING = 10.0;

    /** Every 10% a side's chance creation sits above (below) average raises (lowers) its chances by 8%. */
    private const CHANCE_CREATION_WEIGHT = 0.8;

    /** Every 10% a side's attack rating sits above (below) average raises (lowers) its conversion by 8%. */
    private const ATTACK_WEIGHT = 0.8;

    /** Every 10% a side's defence rating sits above (below) average lowers (raises) the opponent's conversion by 7%. */
    private const DEFENCE_WEIGHT = 0.7;

    /** Lowest multiplier a rating can apply, so very weak sides still create and finish something. */
    private const MIN_RATING_FACTOR = 0.2;

    /** Goalkeeper's share of a side's defensive strength; outfield defending makes up the rest. */
    private const GOALKEEPER_DEFENCE_SHARE = 0.25;

    /** Change in a side's own chances per step of attacking (+) or defensive (-) stance. */
    private const STANCE_SCORING_STEP = 0.15;

    /** Change in the opponent's chances per step of attacking (+) or defensive (-) stance. */
    private const STANCE_CONCEDING_STEP = 0.12;

    private const MIN_EXPECTED_CHANCES = 1.0;

    private const MAX_EXPECTED_CHANCES = 25.0;

    private const MIN_CONVERSION = 0.02;

    private const MAX_CONVERSION = 0.35;

    private const MATCH_MINUTES = 90;

    public function __construct(
        private readonly Randomizer $randomizer = new Randomizer,
    ) {}

    public function simulate(MatchTeam $home, MatchTeam $away): MatchSimulationResult
    {
        $homeExpectedChances = $this->expectedChances($home, $away, self::HOME_ADVANTAGE);
        $awayExpectedChances = $this->expectedChances($away, $home, self::AWAY_DISADVANTAGE);
        $homeConversion = $this->conversion($home, $away);
        $awayConversion = $this->conversion($away, $home);

        $homeChances = $this->poisson($homeExpectedChances);
        $awayChances = $this->poisson($awayExpectedChances);
        $homeGoals = $this->goalsFrom($homeChances, $homeConversion);
        $awayGoals = $this->goalsFrom($awayChances, $awayConversion);

        return new MatchSimulationResult($homeGoals, $awayGoals, [
            'engine_version' => self::ENGINE_VERSION,
            'expected_chances' => [
                'home' => round($homeExpectedChances, 2),
                'away' => round($awayExpectedChances, 2),
            ],
            'expected_goals' => [
                'home' => round($homeExpectedChances * $homeConversion, 2),
                'away' => round($awayExpectedChances * $awayConversion, 2),
            ],
            'chances' => ['home' => $homeChances, 'away' => $awayChances],
            'lineups' => [
                'home' => ['starting' => $home->startingPlayerIds, 'substitutes' => $home->substitutePlayerIds],
                'away' => ['starting' => $away->startingPlayerIds, 'substitutes' => $away->substitutePlayerIds],
            ],
            'events' => $this->goalEvents($home, $homeGoals, $away, $awayGoals),
        ]);
    }

    /**
     * Combined formation tendency and mentality, from -2 (fully defensive) to 2 (fully attacking).
     * A balanced formation played with a balanced mentality, such as a default 4-4-2, is 0.
     */
    public function tacticalStance(MatchTeam $team): int
    {
        $formationStance = match ($team->formationTendency) {
            FormationTendency::DEFENSIVE => -1,
            FormationTendency::BALANCED => 0,
            FormationTendency::ATTACKING => 1,
        };

        $mentalityStance = match ($team->mentality) {
            Mentality::DEFENSIVE => -1,
            Mentality::BALANCED => 0,
            Mentality::ATTACKING => 1,
        };

        return $formationStance + $mentalityStance;
    }

    /**
     * Chances come from the side's creators, its share of the ball and how openly both sides play.
     */
    private function expectedChances(MatchTeam $attacking, MatchTeam $defending, float $venueFactor): float
    {
        $creationFactor = $this->ratingFactor(self::CHANCE_CREATION_WEIGHT * ($attacking->chanceCreation - self::AVERAGE_RATING));
        $possessionFactor = 0.5 + $attacking->midfield / ($attacking->midfield + $defending->midfield);
        $scoringFactor = 1 + self::STANCE_SCORING_STEP * $this->tacticalStance($attacking);
        $concedingFactor = 1 + self::STANCE_CONCEDING_STEP * $this->tacticalStance($defending);

        $expectedChances = self::BASE_CHANCES * $venueFactor * $creationFactor * $possessionFactor * $scoringFactor * $concedingFactor;

        return max(self::MIN_EXPECTED_CHANCES, min(self::MAX_EXPECTED_CHANCES, $expectedChances));
    }

    /**
     * Chance of scoring from each chance: the side's finishing against the opponent's defence and goalkeeper.
     */
    private function conversion(MatchTeam $attacking, MatchTeam $defending): float
    {
        $defensiveStrength = $defending->defence * (1 - self::GOALKEEPER_DEFENCE_SHARE)
            + $defending->goalkeeping * self::GOALKEEPER_DEFENCE_SHARE;
        $attackFactor = $this->ratingFactor(self::ATTACK_WEIGHT * ($attacking->attack - self::AVERAGE_RATING));
        $defenceFactor = $this->ratingFactor(self::DEFENCE_WEIGHT * (self::AVERAGE_RATING - $defensiveStrength));

        return max(self::MIN_CONVERSION, min(self::MAX_CONVERSION, self::BASE_CONVERSION * $attackFactor * $defenceFactor));
    }

    /**
     * Turns a weighted rating gap from average into a multiplier on the base: positive gaps add, negative gaps take away.
     */
    private function ratingFactor(float $weightedGapFromAverage): float
    {
        return max(self::MIN_RATING_FACTOR, 1 + $weightedGapFromAverage / self::AVERAGE_RATING);
    }

    /**
     * Samples a Poisson-distributed count around the expected value (Knuth's method).
     */
    private function poisson(float $expected): int
    {
        $threshold = exp(-$expected);
        $count = 0;
        $probability = $this->randomizer->nextFloat();

        while ($probability > $threshold) {
            $count++;
            $probability *= $this->randomizer->nextFloat();
        }

        return $count;
    }

    private function goalsFrom(int $chances, float $conversion): int
    {
        $goals = 0;

        for ($chance = 0; $chance < $chances; $chance++) {
            if ($this->randomizer->nextFloat() < $conversion) {
                $goals++;
            }
        }

        return $goals;
    }

    /** @return list<array{minute: int, type: string, team: string, club_id: int}> */
    private function goalEvents(MatchTeam $home, int $homeGoals, MatchTeam $away, int $awayGoals): array
    {
        $events = [];

        foreach (['home' => [$home, $homeGoals], 'away' => [$away, $awayGoals]] as $side => [$team, $goals]) {
            for ($goal = 0; $goal < $goals; $goal++) {
                $events[] = [
                    'minute' => $this->randomizer->getInt(1, self::MATCH_MINUTES),
                    'type' => 'goal',
                    'team' => $side,
                    'club_id' => $team->clubId,
                ];
            }
        }

        usort($events, fn (array $first, array $second): int => $first['minute'] <=> $second['minute']);

        return $events;
    }
}
