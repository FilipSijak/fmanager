<?php

namespace App\Services\MatchEngineService;

use App\Services\MatchEngineService\Data\MatchState;
use App\Services\MatchEngineService\Data\MatchTeam;
use App\Services\MatchEngineService\Data\SideState;
use App\Services\TacticsService\FormationTendency;
use App\Services\TacticsService\Mentality;
use Random\Randomizer;

/**
 * Plays a match minute by minute. Each minute both sides create chances from their current ratings, tactics and the
 * ball they have, and convert them against the other side's defence; everything else on the pitch is credited to the
 * players in it. A match can be played to full time in one go or a few minutes at a time with substitutions between.
 */
class MatchSimulationEngine
{
    public const ENGINE_VERSION = 5;

    /** Chances an average side creates over a match against an equal opponent on neutral ground. */
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

    /** Bounds on a side's chances over a whole match at its current rate. */
    private const MIN_EXPECTED_CHANCES = 1.0;

    private const MAX_EXPECTED_CHANCES = 25.0;

    private const MIN_CONVERSION = 0.02;

    private const MAX_CONVERSION = 0.35;

    private readonly TeamRatings $teamRatings;

    private readonly MinuteSimulator $minuteSimulator;

    private readonly Substitutions $substitutions;

    private readonly AiSubstitutions $aiSubstitutions;

    private readonly PlayerMatchRating $playerMatchRating;

    private readonly TacticalChanges $tacticalChanges;

    public function __construct(Randomizer $randomizer = new Randomizer)
    {
        $this->teamRatings = new TeamRatings;
        $this->playerMatchRating = new PlayerMatchRating;
        $this->substitutions = new Substitutions($this->teamRatings);
        $this->minuteSimulator = new MinuteSimulator(new MatchRandom($randomizer), $this->teamRatings);
        $this->aiSubstitutions = new AiSubstitutions($this->substitutions, $this->playerMatchRating);
        $this->tacticalChanges = new TacticalChanges($this->teamRatings);
    }

    /** Plays a whole match between two sides the AI manages. */
    public function simulate(MatchTeam $home, MatchTeam $away): MatchSimulationResult
    {
        $state = $this->kickOff($home, $away);
        $this->play($state, MatchState::FULL_TIME);

        return $this->finish($state);
    }

    /**
     * @param  ?string  $userSide  'home' or 'away' when the user manages that side and makes its substitutions.
     */
    public function kickOff(MatchTeam $home, MatchTeam $away, ?string $userSide = null): MatchState
    {
        $sides = [
            'home' => SideState::kickOff($home, $userSide === 'home'),
            'away' => SideState::kickOff($away, $userSide === 'away'),
        ];
        $expectedChances = [
            'home' => $this->expectedChances($sides, 'home'),
            'away' => $this->expectedChances($sides, 'away'),
        ];

        return new MatchState(0, $sides, [], [
            'expected_chances' => array_map(fn (float $chances): float => round($chances, 2), $expectedChances),
            'expected_goals' => [
                'home' => round($expectedChances['home'] * $this->conversion($sides, 'home'), 2),
                'away' => round($expectedChances['away'] * $this->conversion($sides, 'away'), 2),
            ],
        ]);
    }

    /** Plays on from the current minute up to the given one, letting AI sides make their substitutions on the way. */
    public function play(MatchState $state, int $untilMinute): void
    {
        $untilMinute = min($untilMinute, MatchState::FULL_TIME);

        while ($state->minute < $untilMinute) {
            $minute = $state->minute + 1;
            $sides = $state->sides;

            $this->minuteSimulator->play(
                $state,
                $minute,
                [
                    'home' => $this->expectedChances($sides, 'home') / MatchState::FULL_TIME,
                    'away' => $this->expectedChances($sides, 'away') / MatchState::FULL_TIME,
                ],
                ['home' => $this->conversion($sides, 'home'), 'away' => $this->conversion($sides, 'away')],
                $sides['home']->ratings['midfield'] / ($sides['home']->ratings['midfield'] + $sides['away']->ratings['midfield']),
            );

            $state->minute = $minute;

            if (! $state->isFinished()) {
                $this->aiSubstitutions->apply($state, 'home');
                $this->aiSubstitutions->apply($state, 'away');
            }
        }
    }

    public function substitute(MatchState $state, string $side, int $playerOffId, int $playerOnId): void
    {
        $this->substitutions->make($state, $side, $playerOffId, $playerOnId);
    }

    /**
     * @param  list<array{position: string, player_id: ?int}>  $slots
     */
    public function changeTactics(MatchState $state, string $side, ?int $formationId, FormationTendency $formationTendency, Mentality $mentality, array $slots): void
    {
        $this->tacticalChanges->make($state, $side, $formationId, $formationTendency, $mentality, $slots);
    }

    public function finish(MatchState $state): MatchSimulationResult
    {
        usort($state->events, fn (array $first, array $second): int => $first['minute'] <=> $second['minute']);
        $home = $state->side('home');
        $away = $state->side('away');

        return new MatchSimulationResult($home->goals, $away->goals, [
            'engine_version' => self::ENGINE_VERSION,
            ...$state->kickOffExpectations,
            'chances' => ['home' => $home->chances, 'away' => $away->chances],
            'lineups' => [
                'home' => ['starting' => $home->startingPlayerIds, 'substitutes' => $home->substitutePlayerIds],
                'away' => ['starting' => $away->startingPlayerIds, 'substitutes' => $away->substitutePlayerIds],
            ],
            'events' => $state->events,
        ], [...$this->playerStatRows($state, 'home'), ...$this->playerStatRows($state, 'away')]);
    }

    /**
     * Each player's stats so far, shaped like game_player_stats. Goals conceded and clean sheets belong to the
     * goalkeeper on the pitch at the time; a clean sheet needs the minimum minutes in goal.
     *
     * @return list<array<string, int|float|bool|string>>
     */
    public function playerStatRows(MatchState $state, string $side): array
    {
        $team = $state->side($side);
        $opponent = $state->side($side === 'home' ? 'away' : 'home');
        $result = $team->goals <=> $opponent->goals;
        $rows = [];

        foreach ($team->stats as $playerId => $stats) {
            $minutesPlayed = ($stats['left_minute'] ?? $state->minute) - $stats['entered_minute'];
            $isGoalkeeper = PitchLine::fromPosition($stats['position']) === PitchLine::GOALKEEPER;
            $cleanSheet = $isGoalkeeper && $stats['goals_conceded'] === 0 && $minutesPlayed >= PlayerMatchRating::CLEAN_SHEET_MINUTES;
            $counters = array_diff_key($stats, array_flip(['position', 'started', 'entered_minute', 'left_minute']));

            $rows[] = [
                'club_id' => $team->clubId,
                'player_id' => $playerId,
                'position' => $stats['position'],
                'started' => $stats['started'],
                'minutes_played' => $minutesPlayed,
                ...$counters,
                'clean_sheet' => $cleanSheet,
                'rating' => $this->playerMatchRating->rate($stats, $cleanSheet, $result),
            ];
        }

        return $rows;
    }

    /**
     * Combined formation tendency and mentality, from -2 (fully defensive) to 2 (fully attacking).
     * A balanced formation played with a balanced mentality, such as a default 4-4-2, is 0.
     */
    public function tacticalStance(MatchTeam|SideState $team): int
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
     * Chances a side would create over a whole match at its current rate: from its creators, its share of the ball
     * and how openly both sides play, held back by the opponent's defenders.
     *
     * @param  array{home: SideState, away: SideState}  $sides
     */
    private function expectedChances(array $sides, string $side): float
    {
        $attacking = $sides[$side];
        $defending = $sides[$side === 'home' ? 'away' : 'home'];
        $venueFactor = $side === 'home' ? self::HOME_ADVANTAGE : self::AWAY_DISADVANTAGE;
        $creationFactor = $this->ratingFactor(self::CHANCE_CREATION_WEIGHT * ($attacking->ratings['chanceCreation'] - self::AVERAGE_RATING));
        $possessionFactor = 0.5 + $attacking->ratings['midfield'] / ($attacking->ratings['midfield'] + $defending->ratings['midfield']);
        $scoringFactor = 1 + self::STANCE_SCORING_STEP * $this->tacticalStance($attacking);
        $concedingFactor = 1 + self::STANCE_CONCEDING_STEP * $this->tacticalStance($defending);
        $preventionFactor = $this->ratingFactor(self::CHANCE_PREVENTION_WEIGHT * (self::AVERAGE_RATING - $defending->ratings['defence']));

        $expectedChances = self::BASE_CHANCES * $venueFactor * $creationFactor * $possessionFactor * $scoringFactor * $concedingFactor * $preventionFactor;

        return max(self::MIN_EXPECTED_CHANCES, min(self::MAX_EXPECTED_CHANCES, $expectedChances));
    }

    /**
     * Chance of scoring from each chance: the side's finishing against the opponent's defence and goalkeeper.
     *
     * @param  array{home: SideState, away: SideState}  $sides
     */
    private function conversion(array $sides, string $side): float
    {
        $attacking = $sides[$side];
        $defending = $sides[$side === 'home' ? 'away' : 'home'];
        $defensiveStrength = $defending->ratings['defence'] * (1 - self::GOALKEEPER_DEFENCE_SHARE)
            + $defending->ratings['goalkeeping'] * self::GOALKEEPER_DEFENCE_SHARE;
        $attackFactor = $this->ratingFactor(self::ATTACK_WEIGHT * ($attacking->ratings['attack'] - self::AVERAGE_RATING));
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
