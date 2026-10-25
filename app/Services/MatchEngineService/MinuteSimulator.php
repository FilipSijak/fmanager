<?php

namespace App\Services\MatchEngineService;

use App\Services\MatchEngineService\Data\MatchPlayer;
use App\Services\MatchEngineService\Data\MatchState;
use App\Services\MatchEngineService\Data\SideState;

/**
 * Plays one minute of a match: each side's chances shot by shot, plus passes, tackles, interceptions, fouls, cards and
 * offsides, all credited to the players on the pitch at the time.
 */
class MinuteSimulator
{
    /** Share of chances set up by a team-mate; the rest are solo efforts with no key pass or assist. */
    private const ASSISTED_CHANCE_SHARE = 0.75;

    /** Share of all shots that hit the target; goals always count as on target. */
    private const SHOTS_ON_TARGET_SHARE = 0.35;

    /** Passes both sides attempt together per minute, split by possession (900 a match). */
    private const PASSES_PER_MINUTE = 10.0;

    private const BASE_PASS_COMPLETION = 0.8;

    /** Tackles and interceptions a side makes per minute when the opponent has half the ball (17 and 10 a match). */
    private const TACKLES_PER_MINUTE = 17 / 90;

    private const BASE_TACKLE_SUCCESS = 0.6;

    private const INTERCEPTIONS_PER_MINUTE = 10 / 90;

    private const FOULS_PER_MINUTE = 11 / 90;

    /** Chance a foul by an average-aggression player is booked. */
    private const YELLOW_CARD_PER_FOUL = 0.11;

    private const STRAIGHT_RED_PER_FOUL = 0.004;

    private const OFFSIDES_PER_MINUTE = 2.2 / 90;

    private const AVERAGE_RATING = 10.0;

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

    public function __construct(
        private readonly MatchRandom $random,
        private readonly TeamRatings $teamRatings,
    ) {}

    /**
     * @param  array{home: float, away: float}  $expectedChances  Chances each side is expected to create this minute.
     * @param  array{home: float, away: float}  $conversion  Share of each side's chances that go in.
     * @param  float  $homePossession  Home side's share of the ball this minute, between 0 and 1.
     */
    public function play(MatchState $state, int $minute, array $expectedChances, array $conversion, float $homePossession): void
    {
        $possession = ['home' => $homePossession, 'away' => 1 - $homePossession];

        foreach (['home', 'away'] as $side) {
            $opponent = $this->opponent($side);
            $chances = $this->random->poisson($expectedChances[$side]);

            for ($chance = 0; $chance < $chances; $chance++) {
                $this->playChance($state, $side, $minute, $conversion[$side]);
            }

            $this->playPasses($state->side($side), $possession[$side]);
            $this->playDefending($state->side($side), $possession[$opponent]);
            $this->playFouls($state, $side, $minute);
            $this->playOffsides($state->side($side));
        }
    }

    private function playChance(MatchState $state, string $side, int $minute, float $conversion): void
    {
        $team = $state->side($side);
        $opponent = $state->side($this->opponent($side));
        $shooterId = $this->pickPlayer($team, 'shot', fn (MatchPlayer $player): float => ($player->shooting / self::AVERAGE_RATING) ** 2);
        $creatorId = $this->random->happens(self::ASSISTED_CHANCE_SHARE)
            ? $this->pickPlayer($team, 'key_pass', fn (MatchPlayer $player): float => $player->creating / self::AVERAGE_RATING, except: $shooterId)
            : null;
        $isGoal = $this->random->happens($conversion);
        $isOnTarget = $isGoal || $this->random->happens($this->onTargetShareOfMisses($conversion));

        $team->chances++;
        $this->credit($team, $shooterId, 'shots');
        $this->credit($team, $creatorId, 'key_passes');

        if ($isOnTarget) {
            $this->credit($team, $shooterId, 'shots_on_target');
        }

        if (! $isGoal) {
            if ($isOnTarget) {
                $this->credit($opponent, $opponent->goalkeeperId(), 'saves');
            }

            return;
        }

        $team->goals++;
        $this->credit($team, $shooterId, 'goals');
        $this->credit($team, $creatorId, 'assists');
        $this->credit($opponent, $opponent->goalkeeperId(), 'goals_conceded');

        $state->events[] = [
            'minute' => $minute,
            'type' => 'goal',
            'team' => $side,
            'club_id' => $team->clubId,
            'player_id' => $shooterId,
            'assist_player_id' => $creatorId,
        ];
    }

    /** Share of the shots that do not go in that still hit the target, so all shots average the on-target share. */
    private function onTargetShareOfMisses(float $conversion): float
    {
        return max(0.05, min(0.9, (self::SHOTS_ON_TARGET_SHARE - $conversion) / (1 - $conversion)));
    }

    private function playPasses(SideState $team, float $possession): void
    {
        $expectedPasses = $this->shareOut($team, 'pass', self::PASSES_PER_MINUTE * $possession, fn (MatchPlayer $player): float => $player->passing / self::AVERAGE_RATING);

        foreach ($expectedPasses as $playerId => $expected) {
            $passing = $team->players[$playerId]->passing;
            $attempted = $this->random->poisson($expected);
            $completionRate = $this->clamp(self::BASE_PASS_COMPLETION + ($passing - self::AVERAGE_RATING) * 0.015, 0.55, 0.95);

            $this->credit($team, $playerId, 'passes_attempted', $attempted);
            $this->credit($team, $playerId, 'passes_completed', $this->random->successes($attempted, $completionRate));
        }
    }

    /** The more of the ball the opponent has, the more tackles and interceptions there are to make. */
    private function playDefending(SideState $team, float $opponentPossession): void
    {
        $workload = $opponentPossession * 2;
        $expectedTackles = $this->shareOut($team, 'tackle', self::TACKLES_PER_MINUTE * $workload, fn (MatchPlayer $player): float => $player->tackling / self::AVERAGE_RATING);

        foreach ($expectedTackles as $playerId => $expected) {
            $tackling = $team->players[$playerId]->tackling;
            $attempted = $this->random->poisson($expected);
            $successRate = $this->clamp(self::BASE_TACKLE_SUCCESS + ($tackling - self::AVERAGE_RATING) * 0.02, 0.3, 0.9);

            $this->credit($team, $playerId, 'tackles_attempted', $attempted);
            $this->credit($team, $playerId, 'tackles_won', $this->random->successes($attempted, $successRate));
        }

        $expectedInterceptions = $this->shareOut($team, 'interception', self::INTERCEPTIONS_PER_MINUTE * $workload, fn (MatchPlayer $player): float => $player->intercepting / self::AVERAGE_RATING);

        foreach ($expectedInterceptions as $playerId => $expected) {
            $this->credit($team, $playerId, 'interceptions', $this->random->poisson($expected));
        }
    }

    /**
     * Each foul is committed by one side's player on an opponent and may be booked. A red card, straight or from a
     * second yellow, sends the player off and leaves their place empty.
     */
    private function playFouls(MatchState $state, string $side, int $minute): void
    {
        $team = $state->side($side);
        $opponent = $state->side($this->opponent($side));
        $fouls = $this->random->poisson(self::FOULS_PER_MINUTE);

        for ($foul = 0; $foul < $fouls; $foul++) {
            $foulerId = $this->pickPlayer($team, 'foul', fn (MatchPlayer $player): float => $player->aggression / self::AVERAGE_RATING);

            if ($foulerId === null) {
                return;
            }

            $this->credit($team, $foulerId, 'fouls_committed');
            $this->credit($opponent, $this->pickPlayer($opponent, 'foul_won', fn (MatchPlayer $player): float => $player->dribbling / self::AVERAGE_RATING), 'fouls_won');

            $aggression = $team->players[$foulerId]->aggression / self::AVERAGE_RATING;

            if ($this->random->happens(self::STRAIGHT_RED_PER_FOUL)) {
                $this->sendOff($state, $side, $foulerId, $minute);
            } elseif ($this->random->happens(self::YELLOW_CARD_PER_FOUL * $aggression)) {
                $this->credit($team, $foulerId, 'yellow_cards');
                $state->events[] = $this->cardEvent('yellow_card', $side, $team, $foulerId, $minute);

                if ($team->stats[$foulerId]['yellow_cards'] === 2) {
                    $this->sendOff($state, $side, $foulerId, $minute);
                }
            }
        }
    }

    private function sendOff(MatchState $state, string $side, int $playerId, int $minute): void
    {
        $team = $state->side($side);
        $this->credit($team, $playerId, 'red_cards');
        $team->stats[$playerId]['left_minute'] = $minute;
        $team->sentOff[] = $playerId;

        foreach ($team->slots as $index => $slot) {
            if ($slot['player_id'] === $playerId) {
                $team->slots[$index]['player_id'] = null;
            }
        }

        $team->refreshRatings($this->teamRatings);
        $state->events[] = $this->cardEvent('red_card', $side, $team, $playerId, $minute);
    }

    /** @return array<string, int|string> */
    private function cardEvent(string $type, string $side, SideState $team, int $playerId, int $minute): array
    {
        return ['minute' => $minute, 'type' => $type, 'team' => $side, 'club_id' => $team->clubId, 'player_id' => $playerId];
    }

    /** Players with poorer decisions get caught offside more often. */
    private function playOffsides(SideState $team): void
    {
        $offsides = $this->random->poisson(self::OFFSIDES_PER_MINUTE);

        for ($offside = 0; $offside < $offsides; $offside++) {
            $this->credit($team, $this->pickPlayer($team, 'offside', fn (MatchPlayer $player): float => max(0.1, (20 - $player->decisions) / self::AVERAGE_RATING)), 'offsides');
        }
    }

    /**
     * Splits an expected team total between the players on the pitch by role and ability.
     *
     * @param  callable(MatchPlayer): float  $ability
     * @return array<int, float>
     */
    private function shareOut(SideState $team, string $action, float $teamTotal, callable $ability): array
    {
        $weights = $this->weights($team, $action, $ability);
        $totalWeight = array_sum($weights);

        if ($totalWeight <= 0) {
            return [];
        }

        return array_map(fn (float $weight): float => $teamTotal * $weight / $totalWeight, $weights);
    }

    /** @param callable(MatchPlayer): float $ability */
    private function pickPlayer(SideState $team, string $action, callable $ability, ?int $except = null): ?int
    {
        $weights = $this->weights($team, $action, $ability);

        if ($except !== null) {
            unset($weights[$except]);
        }

        return $this->random->pick($weights);
    }

    /**
     * @param  callable(MatchPlayer): float  $ability
     * @return array<int, float>
     */
    private function weights(SideState $team, string $action, callable $ability): array
    {
        return array_map(
            fn (MatchPlayer $player): float => self::ROLE_WEIGHTS[$action][PositionRole::fromPosition($player->position)->value] * $ability($player),
            $team->onPitch(),
        );
    }

    private function credit(SideState $team, ?int $playerId, string $stat, int $amount = 1): void
    {
        if ($playerId !== null) {
            $team->stats[$playerId][$stat] += $amount;
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
