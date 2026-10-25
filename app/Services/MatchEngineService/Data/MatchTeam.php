<?php

namespace App\Services\MatchEngineService\Data;

use App\Services\TacticsService\FormationTendency;
use App\Services\TacticsService\Mentality;

/**
 * A club's strength going into a match. Ratings use the 1-20 player attribute scale.
 */
final class MatchTeam
{
    public function __construct(
        public readonly int $clubId,
        public readonly float $attack,
        public readonly float $midfield,
        public readonly float $defence,
        public readonly float $goalkeeping,
        public readonly FormationTendency $formationTendency = FormationTendency::BALANCED,
        public readonly Mentality $mentality = Mentality::BALANCED,
    ) {}
}
