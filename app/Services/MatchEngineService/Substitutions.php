<?php

namespace App\Services\MatchEngineService;

use App\Services\MatchEngineService\Data\MatchState;
use App\Services\MatchEngineService\Data\SideState;
use DomainException;

/**
 * Brings a substitute on for a player on the pitch, for the user's side and the AI alike.
 */
class Substitutions
{
    public const MAX_SUBSTITUTIONS = 5;

    public function __construct(
        private readonly TeamRatings $teamRatings,
    ) {}

    /**
     * The substitute takes the replaced player's place in the lineup, except that a goalkeeper coming on while the
     * goal is empty (after a red card) goes in goal and leaves the replaced player's place empty instead.
     */
    public function make(MatchState $state, string $side, int $playerOffId, int $playerOnId): void
    {
        $team = $state->side($side);
        $this->validate($state, $team, $playerOffId, $playerOnId);

        $offSlot = $this->slotOf($team, $playerOffId);
        $incoming = $team->players[$playerOnId];
        $intoEmptyGoal = $team->hasEmptyGoal() && PitchLine::fromPosition($incoming->position) === PitchLine::GOALKEEPER;
        $onSlot = $intoEmptyGoal ? $this->emptyGoalSlot($team) : $offSlot;

        $team->slots[$offSlot]['player_id'] = null;
        $team->slots[$onSlot]['player_id'] = $playerOnId;
        $team->players[$playerOnId] = $incoming->playingAs($team->slots[$onSlot]['position']);
        $team->bench = array_values(array_diff($team->bench, [$playerOnId]));
        $team->stats[$playerOffId]['left_minute'] = $state->minute;
        $team->stats[$playerOnId] = SideState::freshStats($team->slots[$onSlot]['position'], started: false, enteredMinute: $state->minute);
        $team->substitutionsUsed++;
        $team->refreshRatings($this->teamRatings);

        $state->events[] = [
            'minute' => max(1, $state->minute),
            'type' => 'substitution',
            'team' => $side,
            'club_id' => $team->clubId,
            'player_id' => $playerOnId,
            'replaced_player_id' => $playerOffId,
        ];
    }

    private function validate(MatchState $state, SideState $team, int $playerOffId, int $playerOnId): void
    {
        if ($state->isFinished()) {
            throw new DomainException('The match has finished.');
        }

        if ($team->substitutionsUsed >= self::MAX_SUBSTITUTIONS) {
            throw new DomainException('All '.self::MAX_SUBSTITUTIONS.' substitutions have been made.');
        }

        if (! array_key_exists($playerOffId, $team->onPitch())) {
            throw new DomainException('The player coming off is not on the pitch.');
        }

        if (! in_array($playerOnId, $team->bench, true)) {
            throw new DomainException('The player coming on is not available on the bench.');
        }
    }

    private function slotOf(SideState $team, int $playerId): int
    {
        foreach ($team->slots as $index => $slot) {
            if ($slot['player_id'] === $playerId) {
                return $index;
            }
        }

        throw new DomainException('The player coming off is not on the pitch.');
    }

    private function emptyGoalSlot(SideState $team): int
    {
        foreach ($team->slots as $index => $slot) {
            if ($slot['player_id'] === null && PitchLine::fromPosition($slot['position']) === PitchLine::GOALKEEPER) {
                return $index;
            }
        }

        throw new DomainException('The goal is not empty.');
    }
}
