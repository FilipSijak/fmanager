<?php

namespace App\Services\MatchEngineService\Data;

/**
 * What happened on the pitch once the engine has credited every action to a player.
 */
final class PlayedMatch
{
    public function __construct(
        public readonly int $homeGoals,
        public readonly int $awayGoals,
        /** @var list<array{minute: int, type: string, team: string, club_id: int, player_id: ?int, assist_player_id: ?int}> */
        public readonly array $events,
        /** @var list<array<string, int|float|bool|string>> One row per player who played, shaped like game_player_stats. */
        public readonly array $playerStats,
    ) {}
}
