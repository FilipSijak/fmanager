<?php

namespace App\Services\MatchEngineService;

enum PitchLine: string
{
    case GOALKEEPER = 'goalkeeper';
    case DEFENCE = 'defence';
    case MIDFIELD = 'midfield';
    case ATTACK = 'attack';

    /**
     * Groups player and formation slot positions (e.g. "LWB", "AMC", "ST") into the line they play in.
     */
    public static function fromPosition(string $position): self
    {
        return match ($position) {
            'GK' => self::GOALKEEPER,
            'CB', 'LB', 'RB', 'LWB', 'RWB' => self::DEFENCE,
            'LW', 'RW', 'LF', 'RF', 'CF', 'ST' => self::ATTACK,
            default => self::MIDFIELD,
        };
    }
}
