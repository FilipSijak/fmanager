<?php

namespace App\Services\TacticsService\Data;

use App\Services\TacticsService\Mentality;
use App\Services\TacticsService\PassingStyle;
use App\Services\TacticsService\PressingIntensity;
use App\Services\TacticsService\TacklingStyle;

readonly class TeamInstructionsData
{
    public function __construct(
        public Mentality $mentality,
        public PressingIntensity $pressing,
        public PassingStyle $passing,
        public TacklingStyle $tackling,
        public bool $offsideTrap,
        public bool $counterAttack,
        public bool $menBehindBall,
        public ?int $freeKicksLeftPlayerId = null,
        public ?int $freeKicksRightPlayerId = null,
        public ?int $cornersLeftPlayerId = null,
        public ?int $cornersRightPlayerId = null,
        public ?int $playmakerPlayerId = null,
    ) {}

    /**
     * Instructions describing how the manager likes to play, stored on their preference.
     *
     * @return array{mentality: Mentality, pressing: PressingIntensity, passing: PassingStyle, tackling: TacklingStyle, offside_trap: bool, counter_attack: bool, men_behind_ball: bool}
     */
    public function styleAttributes(): array
    {
        return [
            'mentality' => $this->mentality,
            'pressing' => $this->pressing,
            'passing' => $this->passing,
            'tackling' => $this->tackling,
            'offside_trap' => $this->offsideTrap,
            'counter_attack' => $this->counterAttack,
            'men_behind_ball' => $this->menBehindBall,
        ];
    }

    /**
     * Club-specific player roles, stored only on the club tactic.
     *
     * @return array{free_kicks_left_player_id: ?int, free_kicks_right_player_id: ?int, corners_left_player_id: ?int, corners_right_player_id: ?int, playmaker_player_id: ?int}
     */
    public function playerRoleAttributes(): array
    {
        return [
            'free_kicks_left_player_id' => $this->freeKicksLeftPlayerId,
            'free_kicks_right_player_id' => $this->freeKicksRightPlayerId,
            'corners_left_player_id' => $this->cornersLeftPlayerId,
            'corners_right_player_id' => $this->cornersRightPlayerId,
            'playmaker_player_id' => $this->playmakerPlayerId,
        ];
    }
}
