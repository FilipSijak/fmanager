<?php

namespace Tests\Unit\Services\MatchEngineService;

use App\Services\MatchEngineService\Data\MatchPlayer;
use App\Services\MatchEngineService\Data\MatchTeam;
use App\Services\MatchEngineService\MatchSimulationEngine;
use App\Services\MatchEngineService\MatchSimulationResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class MatchSimulationEnginePlayerStatsTest extends TestCase
{
    private const POSITIONS = ['GK', 'LB', 'CB', 'CB', 'RB', 'LM', 'CM', 'CM', 'RM', 'ST', 'ST'];

    #[Test]
    public function credits_every_starter_and_keeps_player_stats_consistent_with_the_score(): void
    {
        $engine = $this->engine(11);

        for ($match = 0; $match < 30; $match++) {
            $result = $engine->simulate($this->team(10, 100), $this->team(20, 200));

            $this->assertCount(22, $result->playerStats);

            foreach (['home' => [10, $result->homeGoals], 'away' => [20, $result->awayGoals]] as $side => [$clubId, $goals]) {
                $rows = $this->rowsOf($result, $clubId);
                $goalEvents = array_values(array_filter($result->summary['events'], fn (array $event): bool => $event['type'] === 'goal' && $event['team'] === $side));

                $this->assertSame($goals, array_sum(array_column($rows, 'goals')));
                $this->assertSame($result->summary['chances'][$side], array_sum(array_column($rows, 'shots')));
                $this->assertGreaterThanOrEqual($goals, array_sum(array_column($rows, 'shots_on_target')));
                $this->assertLessThanOrEqual(array_sum(array_column($rows, 'key_passes')), array_sum(array_column($rows, 'assists')));
                $this->assertCount($goals, $goalEvents);

                foreach ($goalEvents as $event) {
                    $this->assertContains($event['player_id'], array_column($rows, 'player_id'));
                    $this->assertNotSame($event['player_id'], $event['assist_player_id']);
                }

                foreach ($rows as $row) {
                    $this->assertTrue($row['started']);
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
        $engine = $this->engine(3);

        for ($match = 0; $match < 30; $match++) {
            $result = $engine->simulate($this->team(10, 100), $this->team(20, 200));

            foreach ([[10, 20, $result->awayGoals], [20, 10, $result->homeGoals]] as [$clubId, $opponentClubId, $conceded]) {
                $goalkeeper = $this->rowOf($result, $clubId === 10 ? 100 : 200);
                $opponentShotsOnTarget = array_sum(array_column($this->rowsOf($result, $opponentClubId), 'shots_on_target'));

                $this->assertSame('GK', $goalkeeper['position']);
                $this->assertSame($conceded, $goalkeeper['goals_conceded']);
                $this->assertSame($opponentShotsOnTarget - $conceded, $goalkeeper['saves']);
                $this->assertSame($conceded === 0 && $goalkeeper['red_cards'] === 0, $goalkeeper['clean_sheet']);

                foreach ($this->rowsOf($result, $clubId) as $row) {
                    if ($row['position'] !== 'GK') {
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
        $engine = $this->engine(5);
        $totals = [];

        for ($match = 0; $match < 200; $match++) {
            $result = $engine->simulate($this->team(10, 100), $this->team(20, 200));

            foreach ($result->playerStats as $row) {
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
        $engine = $this->engine(9);
        $home = $this->team(10, 100, [
            110 => ['shooting' => 18, 'passing' => 18],
            109 => ['shooting' => 6, 'passing' => 6],
        ]);
        $totals = [];

        for ($match = 0; $match < 200; $match++) {
            $result = $engine->simulate($home, $this->team(20, 200));

            foreach ([109, 110] as $playerId) {
                $row = $this->rowOf($result, $playerId);
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
        $engine = $this->engine(13);
        $home = $this->team(10, 100, [
            106 => ['aggression' => 20],
            107 => ['aggression' => 2],
        ]);
        $totals = [];

        for ($match = 0; $match < 200; $match++) {
            $result = $engine->simulate($home, $this->team(20, 200));

            foreach ([106, 107] as $playerId) {
                $row = $this->rowOf($result, $playerId);
                $totals[$playerId]['fouls'] = ($totals[$playerId]['fouls'] ?? 0) + $row['fouls_committed'];
                $totals[$playerId]['cards'] = ($totals[$playerId]['cards'] ?? 0) + $row['yellow_cards'];
            }
        }

        $this->assertGreaterThan($totals[107]['fouls'] * 3, $totals[106]['fouls']);
        $this->assertGreaterThan($totals[107]['cards'] * 3, $totals[106]['cards']);
    }

    #[Test]
    public function a_red_card_sends_the_player_off_for_the_rest_of_the_match(): void
    {
        $engine = $this->engine(21);
        $aggressiveSide = $this->team(10, 100, array_fill_keys(range(101, 110), ['aggression' => 20]));
        $redCards = 0;

        for ($match = 0; $match < 100; $match++) {
            $result = $engine->simulate($aggressiveSide, $this->team(20, 200));

            foreach ($result->summary['events'] as $event) {
                if ($event['type'] !== 'red_card') {
                    continue;
                }

                $redCards++;
                $row = $this->rowOf($result, $event['player_id']);

                $this->assertSame(1, $row['red_cards']);
                $this->assertSame($event['minute'], $row['minutes_played']);
                $this->assertEmpty(array_filter(
                    $result->summary['events'],
                    fn (array $later): bool => $later['minute'] > $event['minute'] && ($later['player_id'] ?? null) === $event['player_id'],
                ));
            }
        }

        $this->assertGreaterThan(0, $redCards);
    }

    private function engine(int $seed): MatchSimulationEngine
    {
        return new MatchSimulationEngine(new Randomizer(new Mt19937($seed)));
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

    /** @return list<array<string, mixed>> */
    private function rowsOf(MatchSimulationResult $result, int $clubId): array
    {
        return array_values(array_filter($result->playerStats, fn (array $row): bool => $row['club_id'] === $clubId));
    }

    /** @return array<string, mixed> */
    private function rowOf(MatchSimulationResult $result, int $playerId): array
    {
        return array_values(array_filter($result->playerStats, fn (array $row): bool => $row['player_id'] === $playerId))[0];
    }
}
