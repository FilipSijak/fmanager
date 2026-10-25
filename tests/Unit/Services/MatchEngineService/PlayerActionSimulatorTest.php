<?php

namespace Tests\Unit\Services\MatchEngineService;

use App\Services\MatchEngineService\Data\MatchPlayer;
use App\Services\MatchEngineService\Data\MatchTeam;
use App\Services\MatchEngineService\Data\PlayedMatch;
use App\Services\MatchEngineService\MatchRandom;
use App\Services\MatchEngineService\PlayerActionSimulator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class PlayerActionSimulatorTest extends TestCase
{
    private const POSITIONS = ['GK', 'LB', 'CB', 'CB', 'RB', 'LM', 'CM', 'CM', 'RM', 'ST', 'ST'];

    #[Test]
    public function credits_every_starter_and_keeps_player_stats_consistent_with_the_score(): void
    {
        $simulator = $this->simulator(11);
        $home = $this->team(10, 100);
        $away = $this->team(20, 200);

        for ($match = 0; $match < 50; $match++) {
            $played = $simulator->play($home, $away, ['home' => 14, 'away' => 9], ['home' => 0.2, 'away' => 0.15], 0.55);

            $this->assertCount(22, $played->playerStats);

            foreach (['home' => [10, $played->homeGoals, 14], 'away' => [20, $played->awayGoals, 9]] as $side => [$clubId, $goals, $chances]) {
                $rows = array_values(array_filter($played->playerStats, fn (array $row): bool => $row['club_id'] === $clubId));
                $goalEvents = array_values(array_filter($played->events, fn (array $event): bool => $event['team'] === $side));

                $this->assertSame($goals, array_sum(array_column($rows, 'goals')));
                $this->assertSame($chances, array_sum(array_column($rows, 'shots')));
                $this->assertGreaterThanOrEqual($goals, array_sum(array_column($rows, 'shots_on_target')));
                $this->assertLessThanOrEqual(array_sum(array_column($rows, 'key_passes')), array_sum(array_column($rows, 'assists')));
                $this->assertCount($goals, $goalEvents);

                foreach ($goalEvents as $event) {
                    $this->assertContains($event['player_id'], array_column($rows, 'player_id'));
                    $this->assertNotSame($event['player_id'], $event['assist_player_id']);
                }

                foreach ($rows as $row) {
                    $this->assertTrue($row['started']);
                    $this->assertSame(90, $row['minutes_played']);
                    $this->assertLessThanOrEqual($row['passes_attempted'], $row['passes_completed']);
                    $this->assertLessThanOrEqual($row['tackles_attempted'], $row['tackles_won']);
                    $this->assertLessThanOrEqual($row['shots'], $row['shots_on_target']);
                    $this->assertLessThanOrEqual(2, $row['yellow_cards']);
                    $this->assertLessThanOrEqual(1, $row['red_cards']);
                    $this->assertGreaterThanOrEqual(3.0, $row['rating']);
                    $this->assertLessThanOrEqual(10.0, $row['rating']);
                }
            }
        }
    }

    #[Test]
    public function only_goalkeepers_count_goals_conceded_saves_and_clean_sheets(): void
    {
        $simulator = $this->simulator(3);

        for ($match = 0; $match < 50; $match++) {
            $played = $simulator->play($this->team(10, 100), $this->team(20, 200), ['home' => 12, 'away' => 6], ['home' => 0.12, 'away' => 0.12], 0.5);

            foreach ([[10, 200, $played->awayGoals], [20, 100, $played->homeGoals]] as [$clubId, $opponentFirstPlayerId, $conceded]) {
                $opponentRows = $this->rowsFor($played, $opponentFirstPlayerId);
                $goalkeeper = $this->rowFor($played, $clubId === 10 ? 100 : 200);

                $this->assertSame('GK', $goalkeeper['position']);
                $this->assertSame($conceded, $goalkeeper['goals_conceded']);
                $this->assertSame(array_sum(array_column($opponentRows, 'shots_on_target')) - $conceded, $goalkeeper['saves']);
                $this->assertSame($conceded === 0, $goalkeeper['clean_sheet']);

                foreach ($played->playerStats as $row) {
                    if ($row['club_id'] === $clubId && $row['position'] !== 'GK') {
                        $this->assertSame(0, $row['goals_conceded']);
                        $this->assertSame(0, $row['saves']);
                        $this->assertFalse($row['clean_sheet']);
                    }
                }
            }
        }
    }

    #[Test]
    public function positions_shape_who_shoots_creates_passes_and_tackles(): void
    {
        $simulator = $this->simulator(5);
        $totals = [];

        for ($match = 0; $match < 300; $match++) {
            $played = $simulator->play($this->team(10, 100), $this->team(20, 200), ['home' => 12, 'away' => 12], ['home' => 0.13, 'away' => 0.13], 0.5);

            foreach ($played->playerStats as $row) {
                foreach (['shots', 'key_passes', 'passes_attempted', 'tackles_attempted', 'interceptions', 'offsides'] as $stat) {
                    $totals[$row['position']][$stat] = ($totals[$row['position']][$stat] ?? 0) + $row[$stat];
                }
            }
        }

        $perPlayer = fn (string $position, string $stat): float => $totals[$position][$stat] / count(array_keys(self::POSITIONS, $position, true));

        $this->assertGreaterThan($perPlayer('CM', 'shots'), $perPlayer('ST', 'shots'));
        $this->assertGreaterThan($perPlayer('LB', 'shots'), $perPlayer('CM', 'shots'));
        $this->assertSame(0, $totals['GK']['shots']);
        $this->assertGreaterThan($perPlayer('ST', 'key_passes'), $perPlayer('CM', 'key_passes'));
        $this->assertGreaterThan($perPlayer('LB', 'key_passes'), $perPlayer('ST', 'key_passes'));
        $this->assertGreaterThan($perPlayer('CB', 'key_passes'), $perPlayer('LB', 'key_passes'));
        $this->assertGreaterThan($perPlayer('ST', 'passes_attempted'), $perPlayer('CM', 'passes_attempted'));
        $this->assertGreaterThan($perPlayer('CM', 'tackles_attempted'), $perPlayer('CB', 'tackles_attempted'));
        $this->assertGreaterThan($perPlayer('ST', 'interceptions'), $perPlayer('CB', 'interceptions'));
        $this->assertGreaterThan($perPlayer('CM', 'offsides'), $perPlayer('ST', 'offsides'));
    }

    #[Test]
    public function better_finishers_score_more_and_better_passers_complete_more_passes(): void
    {
        $simulator = $this->simulator(9);
        $home = $this->team(10, 100, [
            110 => ['shooting' => 18, 'passing' => 18],
            109 => ['shooting' => 6, 'passing' => 6],
        ]);
        $totals = [];

        for ($match = 0; $match < 300; $match++) {
            $played = $simulator->play($home, $this->team(20, 200), ['home' => 12, 'away' => 12], ['home' => 0.13, 'away' => 0.13], 0.5);

            foreach ([109, 110] as $playerId) {
                $row = $this->rowFor($played, $playerId);
                $totals[$playerId]['goals'] = ($totals[$playerId]['goals'] ?? 0) + $row['goals'];
                $totals[$playerId]['passes_attempted'] = ($totals[$playerId]['passes_attempted'] ?? 0) + $row['passes_attempted'];
                $totals[$playerId]['passes_completed'] = ($totals[$playerId]['passes_completed'] ?? 0) + $row['passes_completed'];
            }
        }

        $completion = fn (int $playerId): float => $totals[$playerId]['passes_completed'] / $totals[$playerId]['passes_attempted'];

        $this->assertGreaterThan($totals[109]['goals'] * 3, $totals[110]['goals']);
        $this->assertGreaterThan($completion(109) + 0.1, $completion(110));
    }

    #[Test]
    public function aggressive_players_foul_and_get_booked_more(): void
    {
        $simulator = $this->simulator(13);
        $home = $this->team(10, 100, [
            106 => ['aggression' => 20],
            107 => ['aggression' => 2],
        ]);
        $totals = [];

        for ($match = 0; $match < 300; $match++) {
            $played = $simulator->play($home, $this->team(20, 200), ['home' => 10, 'away' => 10], ['home' => 0.13, 'away' => 0.13], 0.5);

            foreach ([106, 107] as $playerId) {
                $row = $this->rowFor($played, $playerId);
                $totals[$playerId]['fouls'] = ($totals[$playerId]['fouls'] ?? 0) + $row['fouls_committed'];
                $totals[$playerId]['cards'] = ($totals[$playerId]['cards'] ?? 0) + $row['yellow_cards'];
            }
        }

        $this->assertGreaterThan($totals[107]['fouls'] * 3, $totals[106]['fouls']);
        $this->assertGreaterThan($totals[107]['cards'] * 3, $totals[106]['cards']);
    }

    private function simulator(int $seed): PlayerActionSimulator
    {
        return new PlayerActionSimulator(new MatchRandom(new Randomizer(new Mt19937($seed))));
    }

    /**
     * A 4-4-2 of average players with ids starting at $firstPlayerId (the goalkeeper), with optional rating overrides.
     *
     * @param  array<int, array<string, float>>  $overrides
     */
    private function team(int $clubId, int $firstPlayerId, array $overrides = []): MatchTeam
    {
        $players = [];

        foreach (self::POSITIONS as $index => $position) {
            $playerId = $firstPlayerId + $index;
            $ratings = [
                'shooting' => 10, 'creating' => 10, 'passing' => 10, 'tackling' => 10, 'intercepting' => 10,
                'dribbling' => 10, 'aggression' => 10, 'decisions' => 10, 'goalkeeping' => 10,
                ...$overrides[$playerId] ?? [],
            ];
            $players[] = new MatchPlayer($playerId, $position, ...$ratings);
        }

        return new MatchTeam($clubId, 10, 10, 10, 10, 10, players: $players);
    }

    /** @return array<string, mixed> */
    private function rowFor(PlayedMatch $played, int $playerId): array
    {
        return array_values(array_filter($played->playerStats, fn (array $row): bool => $row['player_id'] === $playerId))[0];
    }

    /** @return list<array<string, mixed>> Rows of the side whose goalkeeper has the given id. */
    private function rowsFor(PlayedMatch $played, int $goalkeeperId): array
    {
        $clubId = $this->rowFor($played, $goalkeeperId)['club_id'];

        return array_values(array_filter($played->playerStats, fn (array $row): bool => $row['club_id'] === $clubId));
    }
}
