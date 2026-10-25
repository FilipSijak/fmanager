<?php

namespace App\Services\MatchEngineService;

use App\Models\GamePlayerStat;
use App\Services\MatchEngineService\Data\MatchPlayer;
use App\Services\MatchEngineService\Data\MatchTeam;
use App\Services\MatchEngineService\Data\PlayedMatch;

/**
 * Plays out each side's chances shot by shot and credits every action in the match to the starter who made it.
 */
final class PlayerActionSimulator
{
    private const MATCH_MINUTES = 90;

    /** Share of chances set up by a team-mate; the rest are solo efforts with no key pass or assist. */
    private const ASSISTED_CHANCE_SHARE = 0.75;

    /** Share of all shots that hit the target; goals always count as on target. */
    private const SHOTS_ON_TARGET_SHARE = 0.35;

    /** Passes both sides attempt together, split by possession. */
    private const MATCH_PASSES = 900.0;

    private const BASE_PASS_COMPLETION = 0.8;

    /** Tackles and interceptions a side makes when the opponent has half the ball; more when it has more. */
    private const BASE_TACKLES = 17.0;

    private const BASE_TACKLE_SUCCESS = 0.6;

    private const BASE_INTERCEPTIONS = 10.0;

    private const FOULS_PER_SIDE = 11.0;

    /** Chance a foul by an average-aggression player is booked. */
    private const YELLOW_CARD_PER_FOUL = 0.11;

    private const STRAIGHT_RED_PER_FOUL = 0.004;

    private const OFFSIDES_PER_SIDE = 2.2;

    private const AVERAGE_RATING = 10.0;

    private const BASE_MATCH_RATING = 6.0;

    /**
     * How often each role is involved in an action, before the player's own ability is applied.
     *
     * @var array<string, array<string, float>>
     */
    private const ROLE_WEIGHTS = [
        'shot' => ['forward' => 1.0, 'midfield' => 0.45, 'wing_back' => 0.15, 'centre_back' => 0.12, 'goalkeeper' => 0.0],
        'key_pass' => ['midfield' => 1.0, 'forward' => 0.6, 'wing_back' => 0.35, 'centre_back' => 0.1, 'goalkeeper' => 0.0],
        'pass' => ['midfield' => 1.0, 'wing_back' => 0.8, 'centre_back' => 0.75, 'forward' => 0.45, 'goalkeeper' => 0.35],
        'tackle' => ['centre_back' => 1.0, 'wing_back' => 0.9, 'midfield' => 0.7, 'forward' => 0.3, 'goalkeeper' => 0.0],
        'interception' => ['centre_back' => 1.0, 'midfield' => 0.75, 'wing_back' => 0.7, 'forward' => 0.25, 'goalkeeper' => 0.05],
        'foul' => ['centre_back' => 0.9, 'midfield' => 0.9, 'wing_back' => 0.8, 'forward' => 0.6, 'goalkeeper' => 0.05],
        'foul_won' => ['forward' => 1.0, 'midfield' => 0.9, 'wing_back' => 0.6, 'centre_back' => 0.3, 'goalkeeper' => 0.05],
        'offside' => ['forward' => 1.0, 'midfield' => 0.2, 'wing_back' => 0.1, 'centre_back' => 0.02, 'goalkeeper' => 0.0],
    ];

    /** @var array<string, MatchTeam> */
    private array $teams = [];

    /** @var array<string, array<int, MatchPlayer>> */
    private array $players = [];

    /** @var array<string, array<int, array<string, int>>> */
    private array $stats = [];

    public function __construct(
        private readonly MatchRandom $random,
    ) {}

    /**
     * @param  array{home: int, away: int}  $chances
     * @param  array{home: float, away: float}  $conversion
     * @param  float  $homePossession  Home side's share of the ball, between 0 and 1.
     */
    public function play(MatchTeam $home, MatchTeam $away, array $chances, array $conversion, float $homePossession): PlayedMatch
    {
        $this->teams = ['home' => $home, 'away' => $away];
        $possession = ['home' => $homePossession, 'away' => 1 - $homePossession];
        $this->players = [];
        $this->stats = [];

        foreach ($this->teams as $side => $team) {
            foreach ($team->players as $player) {
                $this->players[$side][$player->playerId] = $player;
                $this->stats[$side][$player->playerId] = array_fill_keys(GamePlayerStat::COUNTERS, 0);
            }
        }

        $goals = ['home' => 0, 'away' => 0];
        $events = [];

        foreach (['home', 'away'] as $side) {
            for ($chance = 0; $chance < $chances[$side]; $chance++) {
                $goal = $this->playChance($side, $conversion[$side]);

                if ($goal !== null) {
                    $goals[$side]++;
                    $events[] = [
                        'minute' => $this->random->minute(self::MATCH_MINUTES),
                        'type' => 'goal',
                        'team' => $side,
                        'club_id' => $this->teams[$side]->clubId,
                        ...$goal,
                    ];
                }
            }

            $this->playPasses($side, $possession[$side]);
            $this->playDefending($side, $possession[$this->opponent($side)]);
            $this->playFouls($side);
            $this->playOffsides($side);
        }

        usort($events, fn (array $first, array $second): int => $first['minute'] <=> $second['minute']);

        return new PlayedMatch($goals['home'], $goals['away'], $events, $this->playerStatRows($goals));
    }

    /**
     * One shot: who takes it, who set it up, whether it is on target and whether it goes in.
     *
     * @return array{player_id: ?int, assist_player_id: ?int}|null The scorer and assister when it is a goal.
     */
    private function playChance(string $side, float $conversion): ?array
    {
        $shooterId = $this->pickPlayer($side, 'shot', fn (MatchPlayer $player): float => ($player->shooting / self::AVERAGE_RATING) ** 2);
        $creatorId = $this->random->happens(self::ASSISTED_CHANCE_SHARE)
            ? $this->pickPlayer($side, 'key_pass', fn (MatchPlayer $player): float => $player->creating / self::AVERAGE_RATING, except: $shooterId)
            : null;
        $isGoal = $this->random->happens($conversion);
        $isOnTarget = $isGoal || $this->random->happens($this->onTargetShareOfMisses($conversion));

        $this->credit($side, $shooterId, 'shots');
        $this->credit($side, $creatorId, 'key_passes');

        if ($isOnTarget) {
            $this->credit($side, $shooterId, 'shots_on_target');
        }

        if (! $isGoal) {
            if ($isOnTarget) {
                $this->credit($this->opponent($side), $this->goalkeeperId($this->opponent($side)), 'saves');
            }

            return null;
        }

        $this->credit($side, $shooterId, 'goals');
        $this->credit($side, $creatorId, 'assists');

        return ['player_id' => $shooterId, 'assist_player_id' => $creatorId];
    }

    /** Share of the shots that do not go in that still hit the target, so all shots average the on-target share. */
    private function onTargetShareOfMisses(float $conversion): float
    {
        return max(0.05, min(0.9, (self::SHOTS_ON_TARGET_SHARE - $conversion) / (1 - $conversion)));
    }

    private function playPasses(string $side, float $possession): void
    {
        $expectedPasses = $this->shareOut($side, 'pass', self::MATCH_PASSES * $possession, fn (MatchPlayer $player): float => $player->passing / self::AVERAGE_RATING);

        foreach ($expectedPasses as $playerId => $expected) {
            $player = $this->players[$side][$playerId];
            $attempted = $this->random->poisson($expected);
            $completionRate = $this->clamp(self::BASE_PASS_COMPLETION + ($player->passing - self::AVERAGE_RATING) * 0.015, 0.55, 0.95);

            $this->credit($side, $playerId, 'passes_attempted', $attempted);
            $this->credit($side, $playerId, 'passes_completed', $this->random->successes($attempted, $completionRate));
        }
    }

    /** The more of the ball the opponent has, the more tackles and interceptions there are to make. */
    private function playDefending(string $side, float $opponentPossession): void
    {
        $workload = $opponentPossession * 2;
        $expectedTackles = $this->shareOut($side, 'tackle', self::BASE_TACKLES * $workload, fn (MatchPlayer $player): float => $player->tackling / self::AVERAGE_RATING);

        foreach ($expectedTackles as $playerId => $expected) {
            $player = $this->players[$side][$playerId];
            $attempted = $this->random->poisson($expected);
            $successRate = $this->clamp(self::BASE_TACKLE_SUCCESS + ($player->tackling - self::AVERAGE_RATING) * 0.02, 0.3, 0.9);

            $this->credit($side, $playerId, 'tackles_attempted', $attempted);
            $this->credit($side, $playerId, 'tackles_won', $this->random->successes($attempted, $successRate));
        }

        $expectedInterceptions = $this->shareOut($side, 'interception', self::BASE_INTERCEPTIONS * $workload, fn (MatchPlayer $player): float => $player->intercepting / self::AVERAGE_RATING);

        foreach ($expectedInterceptions as $playerId => $expected) {
            $this->credit($side, $playerId, 'interceptions', $this->random->poisson($expected));
        }
    }

    /**
     * Each foul is committed by one side's player on an opponent and may be booked; a second yellow becomes a red.
     * Sent-off players commit no further fouls but, until substitutions exist, are not removed from the match.
     */
    private function playFouls(string $side): void
    {
        $opponent = $this->opponent($side);
        $fouls = $this->random->poisson(self::FOULS_PER_SIDE);

        for ($foul = 0; $foul < $fouls; $foul++) {
            $foulerId = $this->pickPlayer($side, 'foul', fn (MatchPlayer $player): float => $this->stats[$side][$player->playerId]['red_cards'] > 0
                ? 0.0
                : $player->aggression / self::AVERAGE_RATING);

            if ($foulerId === null) {
                return;
            }

            $this->credit($side, $foulerId, 'fouls_committed');
            $this->credit($opponent, $this->pickPlayer($opponent, 'foul_won', fn (MatchPlayer $player): float => $player->dribbling / self::AVERAGE_RATING), 'fouls_won');

            $aggression = $this->players[$side][$foulerId]->aggression / self::AVERAGE_RATING;

            if ($this->random->happens(self::STRAIGHT_RED_PER_FOUL)) {
                $this->credit($side, $foulerId, 'red_cards');
            } elseif ($this->random->happens(self::YELLOW_CARD_PER_FOUL * $aggression)) {
                $this->credit($side, $foulerId, 'yellow_cards');

                if ($this->stats[$side][$foulerId]['yellow_cards'] === 2) {
                    $this->credit($side, $foulerId, 'red_cards');
                }
            }
        }
    }

    /** Players with poorer decisions get caught offside more often. */
    private function playOffsides(string $side): void
    {
        $offsides = $this->random->poisson(self::OFFSIDES_PER_SIDE);

        for ($offside = 0; $offside < $offsides; $offside++) {
            $this->credit($side, $this->pickPlayer($side, 'offside', fn (MatchPlayer $player): float => max(0.1, (20 - $player->decisions) / self::AVERAGE_RATING)), 'offsides');
        }
    }

    /**
     * @param  array{home: int, away: int}  $goals
     * @return list<array<string, int|float|bool|string>>
     */
    private function playerStatRows(array $goals): array
    {
        $rows = [];

        foreach ($this->stats as $side => $players) {
            $conceded = $goals[$this->opponent($side)];
            $result = $goals[$side] <=> $conceded;

            foreach ($players as $playerId => $counters) {
                $player = $this->players[$side][$playerId];
                $isGoalkeeper = PositionRole::fromPosition($player->position) === PositionRole::GOALKEEPER;
                $counters['goals_conceded'] = $isGoalkeeper ? $conceded : 0;
                $cleanSheet = $isGoalkeeper && $conceded === 0;

                $rows[] = [
                    'club_id' => $this->teams[$side]->clubId,
                    'player_id' => $playerId,
                    'position' => $player->position,
                    'started' => true,
                    'minutes_played' => self::MATCH_MINUTES,
                    ...$counters,
                    'clean_sheet' => $cleanSheet,
                    'rating' => $this->matchRating($counters, $cleanSheet, $result),
                ];
            }
        }

        return $rows;
    }

    /**
     * A 3-10 rating starting from 6, moved by the player's contributions and the team's result.
     *
     * @param  array<string, int>  $stats
     */
    private function matchRating(array $stats, bool $cleanSheet, int $result): float
    {
        $passCompletionBonus = $stats['passes_attempted'] >= 5
            ? ($stats['passes_completed'] / $stats['passes_attempted'] - self::BASE_PASS_COMPLETION) * 3
            : 0.0;

        $rating = self::BASE_MATCH_RATING
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

        return round($this->clamp($rating, 3.0, 10.0), 1);
    }

    /**
     * Splits an expected team total between the side's players by role and ability.
     *
     * @param  callable(MatchPlayer): float  $ability
     * @return array<int, float>
     */
    private function shareOut(string $side, string $action, float $teamTotal, callable $ability): array
    {
        $weights = $this->weights($side, $action, $ability);
        $totalWeight = array_sum($weights);

        if ($totalWeight <= 0) {
            return [];
        }

        return array_map(fn (float $weight): float => $teamTotal * $weight / $totalWeight, $weights);
    }

    /** @param callable(MatchPlayer): float $ability */
    private function pickPlayer(string $side, string $action, callable $ability, ?int $except = null): ?int
    {
        $weights = $this->weights($side, $action, $ability);
        unset($weights[$except]);

        return $this->random->pick($weights);
    }

    /**
     * @param  callable(MatchPlayer): float  $ability
     * @return array<int, float>
     */
    private function weights(string $side, string $action, callable $ability): array
    {
        return array_map(
            fn (MatchPlayer $player): float => self::ROLE_WEIGHTS[$action][PositionRole::fromPosition($player->position)->value] * $ability($player),
            $this->players[$side] ?? [],
        );
    }

    private function goalkeeperId(string $side): ?int
    {
        foreach ($this->players[$side] ?? [] as $playerId => $player) {
            if (PositionRole::fromPosition($player->position) === PositionRole::GOALKEEPER) {
                return $playerId;
            }
        }

        return null;
    }

    private function credit(string $side, ?int $playerId, string $stat, int $amount = 1): void
    {
        if ($playerId !== null) {
            $this->stats[$side][$playerId][$stat] += $amount;
        }
    }

    private function opponent(string $side): string
    {
        return $side === 'home' ? 'away' : 'home';
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
