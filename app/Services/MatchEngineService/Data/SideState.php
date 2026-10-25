<?php

namespace App\Services\MatchEngineService\Data;

use App\Models\GamePlayerStat;
use App\Services\MatchEngineService\PitchLine;
use App\Services\MatchEngineService\TeamRatings;
use App\Services\TacticsService\FormationTendency;
use App\Services\TacticsService\Mentality;

/**
 * One side of a match in progress: who is on the pitch, who is left on the bench and what each player has done.
 */
final class SideState
{
    /**
     * @param  list<array{position: string, player_id: ?int}>  $slots  Lineup slots; a null player is an empty slot.
     * @param  list<int>  $bench  Substitutes who can still come on.
     * @param  array<int, MatchPlayer>  $players  Everyone in the matchday squad.
     * @param  array<int, array<string, int|bool|string|null>>  $stats  Players who have played, with their tallies.
     * @param  list<int>  $sentOff
     * @param  array{attack: float, midfield: float, defence: float, goalkeeping: float, chanceCreation: float}  $ratings
     * @param  list<int>  $startingPlayerIds
     * @param  list<int>  $substitutePlayerIds
     */
    public function __construct(
        public readonly int $clubId,
        public FormationTendency $formationTendency,
        public Mentality $mentality,
        public readonly bool $userControlled,
        public array $slots,
        public array $bench,
        public array $players,
        public array $stats,
        public array $ratings,
        public readonly array $startingPlayerIds,
        public readonly array $substitutePlayerIds,
        public array $sentOff = [],
        public int $substitutionsUsed = 0,
        public int $goals = 0,
        public int $chances = 0,
        public ?int $formationId = null,
    ) {}

    public static function kickOff(MatchTeam $team, bool $userControlled): self
    {
        $players = [];
        $stats = [];
        $slots = [];

        foreach ($team->players as $player) {
            $players[$player->playerId] = $player;
            $stats[$player->playerId] = self::freshStats($player->position, started: true, enteredMinute: 0);
            $slots[] = ['position' => $player->position, 'player_id' => $player->playerId];
        }

        foreach ($team->vacantPositions as $position) {
            $slots[] = ['position' => $position, 'player_id' => null];
        }

        foreach ($team->substitutes as $substitute) {
            $players[$substitute->playerId] = $substitute;
        }

        return new self(
            clubId: $team->clubId,
            formationTendency: $team->formationTendency,
            mentality: $team->mentality,
            userControlled: $userControlled,
            slots: $slots,
            bench: array_map(fn (MatchPlayer $substitute): int => $substitute->playerId, $team->substitutes),
            players: $players,
            stats: $stats,
            ratings: [
                'attack' => $team->attack,
                'midfield' => $team->midfield,
                'defence' => $team->defence,
                'goalkeeping' => $team->goalkeeping,
                'chanceCreation' => $team->chanceCreation,
            ],
            startingPlayerIds: $team->startingPlayerIds,
            substitutePlayerIds: $team->substitutePlayerIds,
            formationId: $team->formationId,
        );
    }

    /** @return array<string, int|bool|string|null> */
    public static function freshStats(string $position, bool $started, int $enteredMinute): array
    {
        return [
            ...array_fill_keys(GamePlayerStat::COUNTERS, 0),
            'position' => $position,
            'started' => $started,
            'entered_minute' => $enteredMinute,
            'left_minute' => null,
        ];
    }

    /** @return array<int, MatchPlayer> Players on the pitch, keyed by id. */
    public function onPitch(): array
    {
        $onPitch = [];

        foreach ($this->slots as $slot) {
            if ($slot['player_id'] !== null) {
                $onPitch[$slot['player_id']] = $this->players[$slot['player_id']];
            }
        }

        return $onPitch;
    }

    public function goalkeeperId(): ?int
    {
        foreach ($this->slots as $slot) {
            if ($slot['player_id'] !== null && PitchLine::fromPosition($slot['position']) === PitchLine::GOALKEEPER) {
                return $slot['player_id'];
            }
        }

        return null;
    }

    public function hasEmptyGoal(): bool
    {
        foreach ($this->slots as $slot) {
            if ($slot['player_id'] === null && PitchLine::fromPosition($slot['position']) === PitchLine::GOALKEEPER) {
                return true;
            }
        }

        return false;
    }

    /**
     * Re-rates the side from the players now on the pitch. Only called when the players change, so a side the
     * engine was handed ratings for without players keeps those ratings.
     */
    public function refreshRatings(TeamRatings $teamRatings): void
    {
        $this->ratings = $teamRatings->calculate(array_map(fn (array $slot): array => [
            'position' => $slot['position'],
            'player' => $slot['player_id'] === null ? null : $this->players[$slot['player_id']],
        ], $this->slots));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'club_id' => $this->clubId,
            'formation_tendency' => $this->formationTendency->value,
            'mentality' => $this->mentality->value,
            'user_controlled' => $this->userControlled,
            'slots' => $this->slots,
            'bench' => $this->bench,
            'players' => array_map(fn (MatchPlayer $player): array => $player->toArray(), $this->players),
            'stats' => $this->stats,
            'ratings' => $this->ratings,
            'starting_player_ids' => $this->startingPlayerIds,
            'substitute_player_ids' => $this->substitutePlayerIds,
            'sent_off' => $this->sentOff,
            'substitutions_used' => $this->substitutionsUsed,
            'goals' => $this->goals,
            'chances' => $this->chances,
            'formation_id' => $this->formationId,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $players = [];

        foreach ($data['players'] as $player) {
            $players[(int) $player['playerId']] = MatchPlayer::fromArray($player);
        }

        $stats = [];

        foreach ($data['stats'] as $playerId => $playerStats) {
            $stats[(int) $playerId] = $playerStats;
        }

        return new self(
            clubId: $data['club_id'],
            formationTendency: FormationTendency::from($data['formation_tendency']),
            mentality: Mentality::from($data['mentality']),
            userControlled: $data['user_controlled'],
            slots: $data['slots'],
            bench: $data['bench'],
            players: $players,
            stats: $stats,
            ratings: array_map(floatval(...), $data['ratings']),
            startingPlayerIds: $data['starting_player_ids'],
            substitutePlayerIds: $data['substitute_player_ids'],
            sentOff: $data['sent_off'],
            substitutionsUsed: $data['substitutions_used'],
            goals: $data['goals'],
            chances: $data['chances'],
            formationId: $data['formation_id'] ?? null,
        );
    }
}
