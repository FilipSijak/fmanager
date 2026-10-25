<?php

namespace App\Services\MatchEngineService\Data;

use App\Services\TacticsService\FormationTendency;
use App\Services\TacticsService\Mentality;

/**
 * A club's strength going into a match. Ratings use the 1-20 player attribute scale and come from the starters only.
 */
final class MatchTeam
{
    public function __construct(
        public readonly int $clubId,
        public readonly float $attack,
        public readonly float $midfield,
        public readonly float $defence,
        public readonly float $goalkeeping,
        /** Starters' chance-creating ability averaged by the weight of the position each plays. */
        public readonly float $chanceCreation,
        public readonly FormationTendency $formationTendency = FormationTendency::BALANCED,
        public readonly Mentality $mentality = Mentality::BALANCED,
        /** @var list<int> */
        public readonly array $startingPlayerIds = [],
        /** @var list<int> */
        public readonly array $substitutePlayerIds = [],
        /** @var list<MatchPlayer> Starters in lineup order, each playing their slot's position. */
        public readonly array $players = [],
        /** @var list<MatchPlayer> The selected bench, available to come on. */
        public readonly array $substitutes = [],
        /** @var list<string> Lineup positions the club could not fill; they play as empty slots. */
        public readonly array $vacantPositions = [],
        /** The formation the lineup is laid out in, when the club has one. */
        public readonly ?int $formationId = null,
    ) {}
}
