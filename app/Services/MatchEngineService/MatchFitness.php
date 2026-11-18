<?php

namespace App\Services\MatchEngineService;

use App\Services\MatchEngineService\Data\MatchState;
use App\Services\TrainingService\PlayerFitnessCalculator;

class MatchFitness
{
    public function __construct(
        private readonly MatchRandom $random,
        private readonly TeamRatings $ratings,
        private readonly Substitutions $substitutions,
        private readonly PlayerFitnessCalculator $fitness = new PlayerFitnessCalculator,
    ) {}

    public function advance(MatchState $state): void
    {
        foreach (['home', 'away'] as $side) {
            $team = $state->side($side);
            foreach ($team->onPitch() as $playerId => $player) {
                $minutes = $state->minute - $team->stats[$playerId]['entered_minute'];
                $team->players[$playerId] = $player->fatigued($minutes);
                $condition = $this->fitness->conditionAfterMatch($player->condition, $minutes, $player->stamina, $player->position);
                if (! $this->random->happens($this->fitness->matchInjuryProbability($condition, 1))) {
                    continue;
                }
                $state->events[] = [
                    'minute' => $state->minute, 'type' => 'injury', 'team' => $side,
                    'club_id' => $team->clubId, 'player_id' => $playerId,
                    'injury_days' => $this->random->minute(26) + 2,
                ];
                $replacement = null;
                foreach ($team->bench as $benchId) {
                    if (PitchLine::fromPosition($team->players[$benchId]->position) === PitchLine::fromPosition($player->position)) {
                        $replacement = $benchId;
                        break;
                    }
                }
                if ($replacement === null && $player->position !== 'GK') {
                    foreach ($team->bench as $benchId) {
                        if ($team->players[$benchId]->position !== 'GK') {
                            $replacement = $benchId;
                            break;
                        }
                    }
                }
                if ($replacement !== null && ! $state->isFinished() && $team->substitutionsUsed < Substitutions::MAX_SUBSTITUTIONS) {
                    $this->substitutions->make($state, $side, $playerId, $replacement);
                } else {
                    $team->stats[$playerId]['left_minute'] = $state->minute;
                    foreach ($team->slots as $index => $slot) {
                        if ($slot['player_id'] === $playerId) {
                            $team->slots[$index]['player_id'] = null;
                        }
                    }
                }
            }
            if ($team->players !== []) {
                $team->refreshRatings($this->ratings);
            }
        }
    }
}
