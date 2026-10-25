<?php

namespace Tests\Unit\Services\MatchEngineService;

use App\Services\MatchEngineService\Data\MatchPlayer;
use App\Services\MatchEngineService\Data\MatchState;
use App\Services\MatchEngineService\Data\MatchTeam;
use App\Services\MatchEngineService\MatchSimulationEngine;
use App\Services\MatchEngineService\TeamRatings;
use App\Services\TacticsService\FormationTendency;
use App\Services\TacticsService\Mentality;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class TacticalChangesTest extends TestCase
{
    /** Starters 100-110 in these positions; substitutes 111-117 on the bench. */
    private const POSITIONS = ['GK', 'LB', 'CB', 'CB', 'RB', 'LM', 'CM', 'CM', 'RM', 'ST', 'ST'];

    private const FOUR_THREE_THREE = ['GK', 'LB', 'CB', 'CB', 'RB', 'CM', 'CM', 'CM', 'LW', 'AM', 'RW'];

    #[Test]
    public function moving_players_between_positions_re_rates_the_side(): void
    {
        $engine = $this->engine();
        $state = $engine->kickOff($this->team(10, 100, [109 => ['defending' => 2]]), $this->team(20, 200), userSide: 'home');
        $slots = $this->slots(self::POSITIONS, range(100, 110));
        [$slots[2]['player_id'], $slots[9]['player_id']] = [109, 102];
        $state->side('home')->refreshRatings(new TeamRatings);
        $defenceBefore = $state->side('home')->ratings['defence'];

        $engine->changeTactics($state, 'home', 7, FormationTendency::BALANCED, Mentality::BALANCED, $slots);

        $this->assertSame(['position' => 'CB', 'player_id' => 109], $state->side('home')->slots[2]);
        $this->assertSame('CB', $state->side('home')->players[109]->position);
        $this->assertSame('ST', $state->side('home')->players[102]->position);
        $this->assertLessThan($defenceBefore - 1, $state->side('home')->ratings['defence']);
    }

    #[Test]
    public function a_new_formation_and_mentality_change_how_openly_the_side_plays(): void
    {
        $engine = $this->engine();
        $state = $engine->kickOff($this->team(10, 100), $this->team(20, 200), userSide: 'home');

        $engine->changeTactics($state, 'home', 3, FormationTendency::ATTACKING, Mentality::ATTACKING, $this->slots(self::FOUR_THREE_THREE, range(100, 110)));

        $home = $state->side('home');
        $this->assertSame(3, $home->formationId);
        $this->assertSame(2, $engine->tacticalStance($home));
        $this->assertSame(self::FOUR_THREE_THREE, array_column($home->slots, 'position'));
        $this->assertSame('LW', $home->players[108]->position);
    }

    #[Test]
    public function places_left_by_a_sent_off_player_can_stay_empty(): void
    {
        $engine = $this->engine();
        $state = $engine->kickOff($this->team(10, 100), $this->team(20, 200), userSide: 'home');
        $state->side('home')->slots[10]['player_id'] = null;
        $slots = $this->slots(self::FOUR_THREE_THREE, [...range(100, 109), null]);

        $engine->changeTactics($state, 'home', 3, FormationTendency::ATTACKING, Mentality::BALANCED, $slots);

        $this->assertSame(['position' => 'RW', 'player_id' => null], $state->side('home')->slots[10]);
    }

    /** @return array<string, array{callable(MatchSimulationEngine, MatchState): void, string}> */
    public static function invalidChanges(): array
    {
        $change = fn (array $playerIds, array $positions = self::POSITIONS): callable => fn (MatchSimulationEngine $engine, MatchState $state) => $engine->changeTactics(
            $state, 'home', 1, FormationTendency::BALANCED, Mentality::BALANCED,
            array_map(fn (string $position, ?int $playerId): array => ['position' => $position, 'player_id' => $playerId], $positions, $playerIds),
        );
        $notAllPlaced = 'Every player on the pitch needs exactly one position, and only they can be placed.';

        return [
            'a substitute placed without being brought on' => [$change([...range(100, 109), 111]), $notAllPlaced],
            'a player on the pitch left without a position' => [$change([...range(100, 109), null]), $notAllPlaced],
            'a player placed twice' => [$change([...range(100, 109), 100]), $notAllPlaced],
            'a formation without 11 positions' => [$change(range(100, 109), array_slice(self::POSITIONS, 0, 10)), 'A formation needs 11 positions.'],
            'after full time' => [
                function (MatchSimulationEngine $engine, MatchState $state) use ($change): void {
                    $engine->play($state, 90);
                    $change(range(100, 110))($engine, $state);
                },
                'The match has finished.',
            ],
        ];
    }

    #[Test]
    #[DataProvider('invalidChanges')]
    public function rejects_invalid_changes(callable $change, string $message): void
    {
        $engine = $this->engine();
        $state = $engine->kickOff($this->team(10, 100), $this->team(20, 200), userSide: 'home');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage($message);

        $change($engine, $state);
    }

    private function engine(): MatchSimulationEngine
    {
        return new MatchSimulationEngine(new Randomizer(new Mt19937(31)));
    }

    /**
     * @param  list<string>  $positions
     * @param  list<?int>  $playerIds
     * @return list<array{position: string, player_id: ?int}>
     */
    private function slots(array $positions, array $playerIds): array
    {
        return array_map(fn (string $position, ?int $playerId): array => ['position' => $position, 'player_id' => $playerId], $positions, $playerIds);
    }

    /** @param array<int, array<string, float>> $overrides */
    private function team(int $clubId, int $firstPlayerId, array $overrides = []): MatchTeam
    {
        $player = fn (int $playerId, string $position): MatchPlayer => new MatchPlayer($playerId, $position, ...[
            'shooting' => 10, 'creating' => 10, 'passing' => 10, 'tackling' => 10, 'intercepting' => 10,
            'dribbling' => 10, 'aggression' => 10, 'decisions' => 10, 'goalkeeping' => 10,
            ...$overrides[$playerId] ?? [],
        ]);

        return new MatchTeam(
            $clubId, 10, 10, 10, 10, 10,
            startingPlayerIds: range($firstPlayerId, $firstPlayerId + 10),
            substitutePlayerIds: range($firstPlayerId + 11, $firstPlayerId + 17),
            players: array_map($player, range($firstPlayerId, $firstPlayerId + 10), self::POSITIONS),
            substitutes: array_map($player, range($firstPlayerId + 11, $firstPlayerId + 17), ['GK', 'CB', 'RB', 'CM', 'LM', 'ST', 'ST']),
        );
    }
}
