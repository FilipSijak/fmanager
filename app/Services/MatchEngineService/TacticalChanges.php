<?php

namespace App\Services\MatchEngineService;

use App\Services\ClubService\SquadLineup\SquadLineup;
use App\Services\MatchEngineService\Data\MatchState;
use App\Services\TacticsService\FormationTendency;
use App\Services\TacticsService\Mentality;
use DomainException;

/**
 * Changes a side's formation, mentality and who plays where during a match. Only the players already on the pitch
 * can be moved; bringing someone new on is a substitution.
 */
class TacticalChanges
{
    public function __construct(
        private readonly TeamRatings $teamRatings,
    ) {}

    /**
     * @param  list<array{position: string, player_id: ?int}>  $slots  Every position of the formation and who plays it;
     *                                                                 places left by sent-off players stay empty.
     */
    public function make(MatchState $state, string $side, ?int $formationId, FormationTendency $formationTendency, Mentality $mentality, array $slots): void
    {
        $team = $state->side($side);

        if ($state->isFinished()) {
            throw new DomainException('The match has finished.');
        }

        if (count($slots) !== SquadLineup::STARTING_PLAYERS) {
            throw new DomainException('A formation needs '.SquadLineup::STARTING_PLAYERS.' positions.');
        }

        $assigned = array_values(array_filter(array_column($slots, 'player_id'), fn (?int $playerId): bool => $playerId !== null));
        $onPitch = array_keys($team->onPitch());
        sort($assigned);
        sort($onPitch);

        if ($assigned !== $onPitch) {
            throw new DomainException('Every player on the pitch needs exactly one position, and only they can be placed.');
        }

        $team->slots = array_map(fn (array $slot): array => ['position' => $slot['position'], 'player_id' => $slot['player_id']], $slots);

        foreach ($team->slots as $slot) {
            if ($slot['player_id'] !== null) {
                $team->players[$slot['player_id']] = $team->players[$slot['player_id']]->playingAs($slot['position']);
            }
        }

        $team->formationId = $formationId;
        $team->formationTendency = $formationTendency;
        $team->mentality = $mentality;
        $team->refreshRatings($this->teamRatings);
    }
}
