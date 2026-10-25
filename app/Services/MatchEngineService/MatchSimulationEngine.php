<?php

namespace App\Services\MatchEngineService;

use App\Services\MatchEngineService\Data\MatchTeam;
use App\Services\TacticsService\FormationTendency;
use App\Services\TacticsService\Mentality;
use Random\Randomizer;

class MatchSimulationEngine
{
    public const ENGINE_VERSION = 2;

    /** Goals an average side scores against an equal opponent on neutral ground. */
    private const BASE_GOALS = 1.3;

    private const HOME_ADVANTAGE = 1.12;

    private const AWAY_DISADVANTAGE = 0.92;

    /** How sharply the attack-to-defence ratio separates stronger sides from weaker ones. */
    private const STRENGTH_EXPONENT = 1.5;

    /** Goalkeeper's share of a side's defensive strength; outfield defending makes up the rest. */
    private const GOALKEEPER_DEFENCE_SHARE = 0.25;

    /** Change in a side's own expected goals per step of attacking (+) or defensive (-) stance. */
    private const STANCE_SCORING_STEP = 0.15;

    /** Change in the opponent's expected goals per step of attacking (+) or defensive (-) stance. */
    private const STANCE_CONCEDING_STEP = 0.12;

    private const MIN_EXPECTED_GOALS = 0.05;

    private const MAX_EXPECTED_GOALS = 6.0;

    private const MATCH_MINUTES = 90;

    public function __construct(
        private readonly Randomizer $randomizer = new Randomizer,
    ) {}

    public function simulate(MatchTeam $home, MatchTeam $away): MatchSimulationResult
    {
        $homeExpectedGoals = $this->expectedGoals($home, $away, self::HOME_ADVANTAGE);
        $awayExpectedGoals = $this->expectedGoals($away, $home, self::AWAY_DISADVANTAGE);
        $homeGoals = $this->goalsFor($homeExpectedGoals);
        $awayGoals = $this->goalsFor($awayExpectedGoals);

        return new MatchSimulationResult($homeGoals, $awayGoals, [
            'engine_version' => self::ENGINE_VERSION,
            'expected_goals' => [
                'home' => round($homeExpectedGoals, 2),
                'away' => round($awayExpectedGoals, 2),
            ],
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

    private function expectedGoals(MatchTeam $attacking, MatchTeam $defending, float $venueFactor): float
    {
        $defensiveStrength = $defending->defence * (1 - self::GOALKEEPER_DEFENCE_SHARE)
            + $defending->goalkeeping * self::GOALKEEPER_DEFENCE_SHARE;
        $strengthFactor = ($attacking->attack / $defensiveStrength) ** self::STRENGTH_EXPONENT;
        $possessionFactor = 0.5 + $attacking->midfield / ($attacking->midfield + $defending->midfield);
        $scoringFactor = 1 + self::STANCE_SCORING_STEP * $this->tacticalStance($attacking);
        $concedingFactor = 1 + self::STANCE_CONCEDING_STEP * $this->tacticalStance($defending);

        $expectedGoals = self::BASE_GOALS * $venueFactor * $strengthFactor * $possessionFactor * $scoringFactor * $concedingFactor;

        return max(self::MIN_EXPECTED_GOALS, min(self::MAX_EXPECTED_GOALS, $expectedGoals));
    }

    /**
     * Samples a Poisson-distributed goal count around the expected goals (Knuth's method).
     */
    private function goalsFor(float $expectedGoals): int
    {
        $threshold = exp(-$expectedGoals);
        $goals = 0;
        $probability = $this->randomizer->nextFloat();

        while ($probability > $threshold) {
            $goals++;
            $probability *= $this->randomizer->nextFloat();
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
