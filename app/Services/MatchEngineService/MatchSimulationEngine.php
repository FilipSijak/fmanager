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
    public const ENGINE_VERSION = 4;

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

    /** Every 10% a side's outfield defence sits above (below) average lowers (raises) the opponent's chances by 6%. */
    private const CHANCE_PREVENTION_WEIGHT = 0.6;

    /** Lowest multiplier a rating can apply, so very weak sides still create and finish something. */
    private const MIN_RATING_FACTOR = 0.2;

    /** Goalkeeper's share of a side's defensive strength; outfield defending makes up the rest. */
    private const GOALKEEPER_DEFENCE_SHARE = 0.25;

    /** Change in a side's own chances per step of attacking (+) or defensive (-) stance. */
    private const STANCE_SCORING_STEP = 0.08;

    /** Change in the opponent's chances per step of attacking (+) or defensive (-) stance. */
    private const STANCE_CONCEDING_STEP = 0.06;

    private const MIN_EXPECTED_CHANCES = 1.0;

    private const MAX_EXPECTED_CHANCES = 25.0;

    private const MIN_CONVERSION = 0.02;

    private const MAX_CONVERSION = 0.35;

    private readonly MatchRandom $random;

    private readonly PlayerActionSimulator $playerActions;

    public function __construct(Randomizer $randomizer = new Randomizer)
    {
        $this->random = new MatchRandom($randomizer);
        $this->playerActions = new PlayerActionSimulator($this->random);
    }

    public function simulate(MatchTeam $home, MatchTeam $away): MatchSimulationResult
    {
        $homeExpectedChances = $this->expectedChances($home, $away, self::HOME_ADVANTAGE);
        $awayExpectedChances = $this->expectedChances($away, $home, self::AWAY_DISADVANTAGE);
        $homeConversion = $this->conversion($home, $away);
        $awayConversion = $this->conversion($away, $home);

        $chances = ['home' => $this->random->poisson($homeExpectedChances), 'away' => $this->random->poisson($awayExpectedChances)];
        $played = $this->playerActions->play(
            $home,
            $away,
            $chances,
            ['home' => $homeConversion, 'away' => $awayConversion],
            $home->midfield / ($home->midfield + $away->midfield),
        );

        return new MatchSimulationResult($played->homeGoals, $played->awayGoals, [
            'engine_version' => self::ENGINE_VERSION,
            'expected_chances' => [
                'home' => round($homeExpectedChances, 2),
                'away' => round($awayExpectedChances, 2),
            ],
            'expected_goals' => [
                'home' => round($homeExpectedChances * $homeConversion, 2),
                'away' => round($awayExpectedChances * $awayConversion, 2),
            ],
            'chances' => $chances,
            'lineups' => [
                'home' => ['starting' => $home->startingPlayerIds, 'substitutes' => $home->substitutePlayerIds],
                'away' => ['starting' => $away->startingPlayerIds, 'substitutes' => $away->substitutePlayerIds],
            ],
            'events' => $played->events,
        ], $played->playerStats);
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
     * Chances come from the side's creators, its share of the ball and how openly both sides play, and are held back
     * by the opponent's defenders.
     */
    private function expectedChances(MatchTeam $attacking, MatchTeam $defending, float $venueFactor): float
    {
        $creationFactor = $this->ratingFactor(self::CHANCE_CREATION_WEIGHT * ($attacking->chanceCreation - self::AVERAGE_RATING));
        $possessionFactor = 0.5 + $attacking->midfield / ($attacking->midfield + $defending->midfield);
        $scoringFactor = 1 + self::STANCE_SCORING_STEP * $this->tacticalStance($attacking);
        $concedingFactor = 1 + self::STANCE_CONCEDING_STEP * $this->tacticalStance($defending);
        $preventionFactor = $this->ratingFactor(self::CHANCE_PREVENTION_WEIGHT * (self::AVERAGE_RATING - $defending->defence));

        $expectedChances = self::BASE_CHANCES * $venueFactor * $creationFactor * $possessionFactor * $scoringFactor * $concedingFactor * $preventionFactor;

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
}
