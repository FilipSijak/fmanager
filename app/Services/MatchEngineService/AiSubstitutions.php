<?php

namespace App\Services\MatchEngineService;

use App\Services\MatchEngineService\Data\MatchPlayer;
use App\Services\MatchEngineService\Data\MatchState;
use App\Services\MatchEngineService\Data\SideState;

/**
 * Substitutions a side the user does not control makes on its own: a goalkeeper straight away if its own is sent
 * off, and otherwise its worst performer at set minutes in the second half.
 */
class AiSubstitutions
{
    /** Minutes at which the AI replaces its worst performer, one change each. */
    private const PLANNED_MINUTES = [60, 70, 80];

    public function __construct(
        private readonly Substitutions $substitutions,
        private readonly PlayerMatchRating $playerMatchRating,
    ) {}

    public function apply(MatchState $state, string $side): void
    {
        $team = $state->side($side);

        if ($team->userControlled || $team->substitutionsUsed >= Substitutions::MAX_SUBSTITUTIONS || $team->bench === []) {
            return;
        }

        if ($team->hasEmptyGoal() && $this->replaceSentOffGoalkeeper($state, $side, $team)) {
            return;
        }

        if (in_array($state->minute, self::PLANNED_MINUTES, true)) {
            $this->replaceWorstPerformer($state, $side, $team);
        }
    }

    /** @return bool Whether a goalkeeper came on. */
    private function replaceSentOffGoalkeeper(MatchState $state, string $side, SideState $team): bool
    {
        $goalkeeperId = $this->best($team, $team->bench, fn (MatchPlayer $player): ?float => PitchLine::fromPosition($player->position) === PitchLine::GOALKEEPER ? $player->goalkeeping : null);
        $outfieldIds = array_keys(array_filter($team->onPitch(), fn (MatchPlayer $player): bool => PitchLine::fromPosition($player->position) !== PitchLine::GOALKEEPER));
        $sacrificedId = $this->best($team, $outfieldIds, fn (MatchPlayer $player): float => -$this->liveRating($state, $team, $player->playerId));

        if ($goalkeeperId === null || $sacrificedId === null) {
            return false;
        }

        $this->substitutions->make($state, $side, $sacrificedId, $goalkeeperId);

        return true;
    }

    /** Swaps the outfield player rated lowest so far for the bench player best suited to their line. */
    private function replaceWorstPerformer(MatchState $state, string $side, SideState $team): void
    {
        $outfieldIds = array_keys(array_filter($team->onPitch(), fn (MatchPlayer $player): bool => PitchLine::fromPosition($player->position) !== PitchLine::GOALKEEPER));
        $playerOffId = $this->best($team, $outfieldIds, fn (MatchPlayer $player): float => -$this->liveRating($state, $team, $player->playerId));

        if ($playerOffId === null) {
            return;
        }

        $line = PitchLine::fromPosition($team->players[$playerOffId]->position);
        $playerOnId = $this->best($team, $team->bench, fn (MatchPlayer $player): ?float => match (true) {
            PitchLine::fromPosition($player->position) === PitchLine::GOALKEEPER => null,
            PitchLine::fromPosition($player->position) === $line => 100 + $this->lineAbility($player, $line),
            default => $this->lineAbility($player, $line),
        });

        if ($playerOnId !== null) {
            $this->substitutions->make($state, $side, $playerOffId, $playerOnId);
        }
    }

    private function liveRating(MatchState $state, SideState $team, int $playerId): float
    {
        $opponent = $state->sides['home'] === $team ? $state->side('away') : $state->side('home');

        return $this->playerMatchRating->rate($team->stats[$playerId], false, $team->goals <=> $opponent->goals);
    }

    private function lineAbility(MatchPlayer $player, PitchLine $line): float
    {
        return match ($line) {
            PitchLine::GOALKEEPER => $player->goalkeeping,
            PitchLine::DEFENCE => $player->defending,
            PitchLine::MIDFIELD => $player->playmaking,
            PitchLine::ATTACK => $player->shooting,
        };
    }

    /**
     * The player with the highest score, skipping players the scorer returns null for.
     *
     * @param  list<int>  $playerIds
     * @param  callable(MatchPlayer): ?float  $score
     */
    private function best(SideState $team, array $playerIds, callable $score): ?int
    {
        $bestId = null;
        $bestScore = null;

        foreach ($playerIds as $playerId) {
            $playerScore = $score($team->players[$playerId]);

            if ($playerScore !== null && ($bestScore === null || $playerScore > $bestScore)) {
                $bestId = $playerId;
                $bestScore = $playerScore;
            }
        }

        return $bestId;
    }
}
