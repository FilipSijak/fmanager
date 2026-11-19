<?php

namespace Tests\Unit;

use App\Services\MatchEngineService\Data\MatchPlayer;
use App\Services\MatchEngineService\Data\MatchState;
use App\Services\MatchEngineService\Data\MatchTeam;
use App\Services\MatchEngineService\MatchFitness;
use App\Services\MatchEngineService\MatchRandom;
use App\Services\MatchEngineService\MatchSimulationEngine;
use App\Services\MatchEngineService\Substitutions;
use App\Services\MatchEngineService\TeamRatings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\Engine;
use Random\Randomizer;

class MatchFitnessTest extends TestCase
{
    /** @return array<string, array{int, bool, int, ?int, string}> */
    public static function replacements(): array
    {
        return [
            'bench player replaces injured starter' => [0, true, 30, 2, 'CM'],
            'exhausted substitutions leave an empty slot' => [5, true, 30, null, 'CM'],
            'reserve goalkeeper is not used for an outfield injury' => [0, true, 30, null, 'GK'],
            'empty bench leaves an empty slot' => [0, false, 30, null, 'CM'],
            'full time does not trigger another substitution' => [0, true, 90, null, 'CM'],
        ];
    }

    #[Test]
    #[DataProvider('replacements')]
    public function injuries_force_players_off_and_respect_substitution_limits(int $used, bool $hasBench, int $minute, ?int $replacement, string $benchPosition): void
    {
        $player = $this->player(1);
        $bench = $hasBench ? [$this->player(2, $benchPosition)] : [];
        $home = new MatchTeam(1, 10, 10, 10, 10, 10, startingPlayerIds: [1], substitutePlayerIds: $hasBench ? [2] : [], players: [$player], substitutes: $bench);
        $away = new MatchTeam(2, 10, 10, 10, 10, 10);
        $state = (new MatchSimulationEngine)->kickOff($home, $away, 'home');
        $state->minute = $minute;
        $state->side('home')->substitutionsUsed = $used;
        $random = new MatchRandom(new Randomizer(new class implements Engine
        {
            public function generate(): string
            {
                return str_repeat("\0", 8);
            }
        }));
        $ratings = new TeamRatings;

        (new MatchFitness($random, $ratings, new Substitutions($ratings)))->advance($state);

        $team = $state->side('home');
        $this->assertSame($replacement, $team->slots[0]['player_id']);
        $this->assertSame($minute, $team->stats[1]['left_minute']);
        $this->assertSame('injury', $state->events[0]['type']);
        $this->assertSame(3, $state->events[0]['injury_days']);
        $this->assertSame($used + ($replacement === null ? 0 : 1), $team->substitutionsUsed);
        $resumed = MatchState::fromArray($state->toArray());
        $this->assertArrayNotHasKey(1, $resumed->side('home')->onPitch());
    }

    private function player(int $id, string $position = 'CM'): MatchPlayer
    {
        return new MatchPlayer($id, $position, 10, 10, 10, 10, 10, 10, 10, 10, 10);
    }
}
