<?php

namespace Tests\Unit\Services\MatchEngineService;

use App\Services\MatchEngineService\Data\MatchPlayer;
use App\Services\MatchEngineService\Data\MatchState;
use App\Services\MatchEngineService\Data\MatchTeam;
use App\Services\MatchEngineService\MatchSimulationEngine;
use App\Services\MatchEngineService\TeamRatings;
use DomainException;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class SubstitutionsTest extends TestCase
{
    /** Starters 100-110 in these positions. */
    private const POSITIONS = ['GK', 'LB', 'CB', 'CB', 'RB', 'LM', 'CM', 'CM', 'RM', 'ST', 'ST'];

    /** Substitutes 111-117 in these natural positions. */
    private const BENCH_POSITIONS = ['GK', 'CB', 'RB', 'CM', 'LM', 'ST', 'ST'];

    #[Test]
    public function the_substitute_plays_the_replaced_players_position_for_the_rest_of_the_match(): void
    {
        $engine = $this->engine();
        $state = $engine->kickOff($this->team(10, 100), $this->team(20, 200), userSide: 'home');
        $engine->play($state, 30);

        $engine->substitute($state, 'home', 109, 114);
        $engine->play($state, 90);
        $result = $engine->finish($state);

        $rows = collect($result->playerStats)->keyBy('player_id');
        $this->assertSame(['position' => 'ST', 'started' => true, 'minutes_played' => 30], Arr::only($rows[109], ['position', 'started', 'minutes_played']));
        $this->assertSame(['position' => 'ST', 'started' => false, 'minutes_played' => 60], Arr::only($rows[114], ['position', 'started', 'minutes_played']));
        $this->assertContains(
            ['minute' => 30, 'type' => 'substitution', 'team' => 'home', 'club_id' => 10, 'player_id' => 114, 'replaced_player_id' => 109],
            $result->summary['events'],
        );
        $this->assertSame(1, $state->side('home')->substitutionsUsed);
        $this->assertNotContains(114, $state->side('home')->bench);
    }

    #[Test]
    public function a_better_substitute_lifts_the_teams_ratings(): void
    {
        $engine = $this->engine();
        $home = $this->team(10, 100, [109 => ['shooting' => 2], 116 => ['shooting' => 18]]);
        $state = $engine->kickOff($home, $this->team(20, 200), userSide: 'home');
        $state->side('home')->refreshRatings(new TeamRatings);
        $attackBefore = $state->side('home')->ratings['attack'];

        $engine->substitute($state, 'home', 109, 116);

        $this->assertGreaterThan($attackBefore + 2, $state->side('home')->ratings['attack']);
    }

    #[Test]
    public function a_goalkeeper_coming_on_after_a_red_card_goes_in_goal(): void
    {
        $engine = $this->engine();
        $state = $engine->kickOff($this->team(10, 100), $this->team(20, 200), userSide: 'home');
        $engine->play($state, 40);
        $state->side('home')->slots[0]['player_id'] = null;
        $state->side('home')->stats[100]['left_minute'] = 40;

        $engine->substitute($state, 'home', 102, 111);

        $this->assertSame(['position' => 'GK', 'player_id' => 111], $state->side('home')->slots[0]);
        $this->assertSame(['position' => 'CB', 'player_id' => null], $state->side('home')->slots[2]);
        $this->assertSame(111, $state->side('home')->goalkeeperId());
    }

    /** @return array<string, array{callable(MatchSimulationEngine, MatchState): void, string}> */
    public static function invalidSubstitutions(): array
    {
        return [
            'player coming off is not on the pitch' => [
                fn (MatchSimulationEngine $engine, MatchState $state) => $engine->substitute($state, 'home', 115, 116),
                'The player coming off is not on the pitch.',
            ],
            'player coming on is not on the bench' => [
                fn (MatchSimulationEngine $engine, MatchState $state) => $engine->substitute($state, 'home', 109, 110),
                'The player coming on is not available on the bench.',
            ],
            'substituted player coming back on' => [
                function (MatchSimulationEngine $engine, MatchState $state): void {
                    $engine->substitute($state, 'home', 109, 116);
                    $engine->substitute($state, 'home', 110, 109);
                },
                'The player coming on is not available on the bench.',
            ],
            'a sixth substitution' => [
                function (MatchSimulationEngine $engine, MatchState $state): void {
                    foreach ([[101, 112], [102, 113], [103, 114], [104, 115], [105, 116], [106, 117]] as [$off, $on]) {
                        $engine->substitute($state, 'home', $off, $on);
                    }
                },
                'All 5 substitutions have been made.',
            ],
            'after full time' => [
                function (MatchSimulationEngine $engine, MatchState $state): void {
                    $engine->play($state, 90);
                    $engine->substitute($state, 'home', 109, 116);
                },
                'The match has finished.',
            ],
        ];
    }

    #[Test]
    #[DataProvider('invalidSubstitutions')]
    public function rejects_invalid_substitutions(callable $substitute, string $message): void
    {
        $engine = $this->engine();
        $state = $engine->kickOff($this->team(10, 100), $this->team(20, 200), userSide: 'home');
        $engine->play($state, 30);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage($message);

        $substitute($engine, $state);
    }

    private function engine(): MatchSimulationEngine
    {
        return new MatchSimulationEngine(new Randomizer(new Mt19937(17)));
    }

    /**
     * Average starters from $firstPlayerId and a seven-player bench after them, with optional rating overrides.
     *
     * @param  array<int, array<string, float>>  $overrides
     */
    private function team(int $clubId, int $firstPlayerId, array $overrides = []): MatchTeam
    {
        $player = fn (int $playerId, string $position): MatchPlayer => new MatchPlayer($playerId, $position, ...[
            'shooting' => 10, 'creating' => 10, 'passing' => 10, 'tackling' => 10, 'intercepting' => 10,
            'dribbling' => 10, 'aggression' => 10, 'decisions' => 10, 'goalkeeping' => 10,
            ...$overrides[$playerId] ?? [],
        ]);
        $starters = array_map($player, range($firstPlayerId, $firstPlayerId + 10), self::POSITIONS);
        $substitutes = array_map($player, range($firstPlayerId + 11, $firstPlayerId + 17), self::BENCH_POSITIONS);

        return new MatchTeam(
            $clubId, 10, 10, 10, 10, 10,
            startingPlayerIds: range($firstPlayerId, $firstPlayerId + 10),
            substitutePlayerIds: range($firstPlayerId + 11, $firstPlayerId + 17),
            players: $starters,
            substitutes: $substitutes,
        );
    }
}
