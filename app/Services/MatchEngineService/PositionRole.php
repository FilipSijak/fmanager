<?php

namespace App\Services\MatchEngineService;

/**
 * The role a lineup position plays in the engine, finer than its pitch line: full backs and wing backs are told apart
 * from centre backs.
 */
enum PositionRole: string
{
    case GOALKEEPER = 'goalkeeper';
    case CENTRE_BACK = 'centre_back';
    case WING_BACK = 'wing_back';
    case MIDFIELD = 'midfield';
    case FORWARD = 'forward';

    public static function fromPosition(string $position): self
    {
        return match (true) {
            $position === 'GK' => self::GOALKEEPER,
            $position === 'CB' => self::CENTRE_BACK,
            in_array($position, ['LB', 'RB', 'LWB', 'RWB'], true) => self::WING_BACK,
            PitchLine::fromPosition($position) === PitchLine::ATTACK => self::FORWARD,
            default => self::MIDFIELD,
        };
    }
}
