<?php

namespace Tests\Unit\Services\MatchEngineService;

use App\Services\MatchEngineService\Data\MatchPlayer;
use App\Services\MatchEngineService\Data\MatchTeam;
use App\Services\MatchEngineService\MatchSimulationEngine;
use App\Services\MatchEngineService\PitchLine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class AiSubstitutionsTest extends TestCase
{
    private const POSITIONS = ['GK', 'LB', 'CB', 'CB', 'RB', 'LM', 'CM', 'CM', 'RM', 'ST', 'ST'];

    private const BENCH_POSITIONS = ['GK', 'CB', 'RB', 'CM', 'LM', 'ST', 'ST'];

    #[Test]
    public function the_ai_changes_its_worst_performers_at_60_70_and_80_minutes_but_never_for_the_user(): void
    {
        $engine = $this->engine();
        $state = $engine->kickOff($this->team(10, 100), $this->team(20, 200), userSide: 'home');

        $engine->play($state, 90);

        $substitutions = array_values(array_filter($state->events, fn (array $event): bool => $event['type'] === 'substitution'));
        $this->assertSame([60, 70, 80], array_column($substitutions, 'minute'));
        $this->assertSame(['away', 'away', 'away'], array_column($substitutions, 'team'));
    }

    #[Test]
    public function the_ai_brings_on_a_substitute_from_the_replaced_players_line(): void
    {
        $engine = $this->engine();
        $away = $this->team(20, 200);
        $state = $engine->kickOff($this->team(10, 100), $away);

        $engine->play($state, 90);

        $naturalPositions = collect([...$away->players, ...$away->substitutes])->mapWithKeys(fn (MatchPlayer $player): array => [$player->playerId => $player->position]);

        foreach (array_filter($state->events, fn (array $event): bool => $event['type'] === 'substitution' && $event['team'] === 'away') as $substitution) {
            $this->assertSame(
                PitchLine::fromPosition($naturalPositions[$substitution['replaced_player_id']]),
                PitchLine::fromPosition($naturalPositions[$substitution['player_id']]),
            );
        }
    }

    #[Test]
    public function the_ai_replaces_a_sent_off_goalkeeper_straight_away(): void
    {
        $engine = $this->engine();
        $state = $engine->kickOff($this->team(10, 100), $this->team(20, 200));
        $engine->play($state, 20);
        $state->side('away')->slots[0]['player_id'] = null;
        $state->side('away')->stats[200]['left_minute'] = 20;

        $engine->play($state, 21);

        $this->assertSame(211, $state->side('away')->goalkeeperId());
        $this->assertSame(1, $state->side('away')->substitutionsUsed);
    }

    private function engine(): MatchSimulationEngine
    {
        return new MatchSimulationEngine(new Randomizer(new Mt19937(29)));
    }

    /** Average starters from $firstPlayerId and a seven-player bench after them. */
    private function team(int $clubId, int $firstPlayerId): MatchTeam
    {
        $player = fn (int $playerId, string $position): MatchPlayer => new MatchPlayer($playerId, $position, 10, 10, 10, 10, 10, 10, 10, 10, 10);

        return new MatchTeam(
            $clubId, 10, 10, 10, 10, 10,
            startingPlayerIds: range($firstPlayerId, $firstPlayerId + 10),
            substitutePlayerIds: range($firstPlayerId + 11, $firstPlayerId + 17),
            players: array_map($player, range($firstPlayerId, $firstPlayerId + 10), self::POSITIONS),
            substitutes: array_map($player, range($firstPlayerId + 11, $firstPlayerId + 17), self::BENCH_POSITIONS),
        );
    }
}
