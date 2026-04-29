<?php

namespace Tests\Integration\LeagueSchedule;

use App\Services\LeagueScheduleService\LeagueScheduleService;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeagueScheduleServiceTest extends TestCase
{
    /** @var int[] */
    private array $twentyClubs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->twentyClubs = range(1, 20);
    }

    // -------------------------------------------------------------------------
    //  (a) Total fixture count is 380
    // -------------------------------------------------------------------------

    #[Test]
    public function it_generates_exactly_380_fixtures(): void
    {
        $service  = new LeagueScheduleService(2025);
        $fixtures = $service->generateSchedule($this->twentyClubs);

        $this->assertCount(380, $fixtures);
    }

    // -------------------------------------------------------------------------
    //  (b) Every pair of distinct clubs appears exactly twice, home/away swapped
    // -------------------------------------------------------------------------

    #[Test]
    public function every_club_pair_appears_exactly_twice_with_home_away_swapped(): void
    {
        $service  = new LeagueScheduleService(2025);
        $fixtures = $service->generateSchedule($this->twentyClubs);

        // Build a lookup: pairKey => [[home, away], ...]
        $pairings = [];
        foreach ($fixtures as $f) {
            $key = min($f['home_club_id'], $f['away_club_id'])
                 . '-'
                 . max($f['home_club_id'], $f['away_club_id']);

            $pairings[$key][] = [$f['home_club_id'], $f['away_club_id']];
        }

        // There should be exactly C(20,2) = 190 unique pairs.
        $this->assertCount(190, $pairings);

        foreach ($pairings as $key => $appearances) {
            $this->assertCount(
                2,
                $appearances,
                "Pair {$key} should appear exactly twice."
            );

            // The two appearances must have home and away swapped.
            [$h1, $a1] = $appearances[0];
            [$h2, $a2] = $appearances[1];

            $this->assertSame(
                $h1,
                $a2,
                "Second fixture for pair {$key} should have home/away swapped."
            );
            $this->assertSame(
                $a1,
                $h2,
                "Second fixture for pair {$key} should have home/away swapped."
            );
        }
    }

    // -------------------------------------------------------------------------
    //  (c) Every fixture date is a Saturday or Sunday
    // -------------------------------------------------------------------------

    #[Test]
    public function every_fixture_date_falls_on_a_saturday_or_sunday(): void
    {
        $service  = new LeagueScheduleService(2025);
        $fixtures = $service->generateSchedule($this->twentyClubs);

        foreach ($fixtures as $f) {
            /** @var DateTimeImmutable $date */
            $date = $f['date'];
            $dow  = (int) $date->format('N'); // 6=Saturday, 7=Sunday

            $this->assertContains(
                $dow,
                [6, 7],
                "Fixture date {$date->format('Y-m-d')} (round {$f['round']}) must be a Saturday or Sunday."
            );
        }
    }

    // -------------------------------------------------------------------------
    //  (d) Round count is 38
    // -------------------------------------------------------------------------

    #[Test]
    public function there_are_exactly_38_rounds(): void
    {
        $service  = new LeagueScheduleService(2025);
        $fixtures = $service->generateSchedule($this->twentyClubs);

        $rounds = array_unique(array_column($fixtures, 'round'));
        sort($rounds);

        $this->assertCount(38, $rounds);
        $this->assertSame(1, min($rounds));
        $this->assertSame(38, max($rounds));
    }

    // -------------------------------------------------------------------------
    //  (e) Each club appears exactly once per round
    // -------------------------------------------------------------------------

    #[Test]
    public function each_club_appears_exactly_once_per_round(): void
    {
        $service  = new LeagueScheduleService(2025);
        $fixtures = $service->generateSchedule($this->twentyClubs);

        // Group fixtures by round.
        $byRound = [];
        foreach ($fixtures as $f) {
            $byRound[$f['round']][] = $f;
        }

        foreach ($byRound as $round => $roundFixtures) {
            $clubsInRound = [];
            foreach ($roundFixtures as $f) {
                $clubsInRound[] = $f['home_club_id'];
                $clubsInRound[] = $f['away_club_id'];
            }

            $this->assertCount(
                20,
                array_unique($clubsInRound),
                "Each of the 20 clubs must appear exactly once in round {$round}."
            );

            $this->assertCount(
                20,
                $clubsInRound,
                "Round {$round} must contain exactly 20 club appearances (10 fixtures × 2 clubs)."
            );
        }
    }

    // -------------------------------------------------------------------------
    //  (f) Each half independently contains all 190 unique pairings exactly once
    // -------------------------------------------------------------------------

    #[Test]
    public function each_half_independently_contains_all_190_unique_pairings_exactly_once(): void
    {
        $service  = new LeagueScheduleService(2025);
        $fixtures = $service->generateSchedule($this->twentyClubs);

        $firstHalfPairings  = [];
        $secondHalfPairings = [];

        foreach ($fixtures as $f) {
            $key = $f['home_club_id'] . '->' . $f['away_club_id'];

            if ($f['round'] <= 19) {
                $firstHalfPairings[$key] = ($firstHalfPairings[$key] ?? 0) + 1;
            } else {
                $secondHalfPairings[$key] = ($secondHalfPairings[$key] ?? 0) + 1;
            }
        }

        // Each half must have exactly 190 distinct directed pairings.
        $this->assertCount(
            190,
            $firstHalfPairings,
            'First half (rounds 1–19) must contain exactly 190 unique directed pairings.'
        );
        $this->assertCount(
            190,
            $secondHalfPairings,
            'Second half (rounds 20–38) must contain exactly 190 unique directed pairings.'
        );

        // Each directed pairing must appear exactly once per half.
        foreach ($firstHalfPairings as $key => $count) {
            $this->assertSame(
                1,
                $count,
                "First half: pairing {$key} must appear exactly once."
            );
        }

        foreach ($secondHalfPairings as $key => $count) {
            $this->assertSame(
                1,
                $count,
                "Second half: pairing {$key} must appear exactly once."
            );
        }

        // The second half must be the mirror of the first half (home/away swapped).
        foreach ($firstHalfPairings as $key => $_) {
            [$home, $away] = explode('->', $key);
            $reversedKey   = $away . '->' . $home;

            $this->assertArrayHasKey(
                $reversedKey,
                $secondHalfPairings,
                "Second half must contain the reverse of first-half pairing {$key}."
            );
        }
    }

    // -------------------------------------------------------------------------
    //  (g) Saturday/Sunday split is between 4 and 6 for every round
    // -------------------------------------------------------------------------

    #[Test]
    public function every_round_has_between_4_and_6_fixtures_on_each_day(): void
    {
        $service  = new LeagueScheduleService(2025);
        $fixtures = $service->generateSchedule($this->twentyClubs);

        $byRound = [];
        foreach ($fixtures as $f) {
            $byRound[$f['round']][] = $f;
        }

        foreach ($byRound as $round => $roundFixtures) {
            $satCount = 0;
            $sunCount = 0;

            foreach ($roundFixtures as $f) {
                $dow = (int) $f['date']->format('N');
                if ($dow === 6) {
                    $satCount++;
                } else {
                    $sunCount++;
                }
            }

            $this->assertGreaterThanOrEqual(
                4,
                $satCount,
                "Round {$round}: Saturday fixture count ({$satCount}) must be at least 4."
            );
            $this->assertLessThanOrEqual(
                6,
                $satCount,
                "Round {$round}: Saturday fixture count ({$satCount}) must be at most 6."
            );
            $this->assertGreaterThanOrEqual(
                4,
                $sunCount,
                "Round {$round}: Sunday fixture count ({$sunCount}) must be at least 4."
            );
            $this->assertLessThanOrEqual(
                6,
                $sunCount,
                "Round {$round}: Sunday fixture count ({$sunCount}) must be at most 6."
            );
            $this->assertSame(
                10,
                $satCount + $sunCount,
                "Round {$round}: total fixture count must be 10."
            );
        }
    }

    // -------------------------------------------------------------------------
    //  (h) Streak and balance invariants hold across the round 19 → round 20 boundary
    // -------------------------------------------------------------------------

    #[Test]
    public function home_away_balance_invariant_holds_across_the_half_boundary(): void
    {
        $service  = new LeagueScheduleService(2025);
        $fixtures = $service->generateSchedule($this->twentyClubs);

        $byRound = [];
        foreach ($fixtures as $f) {
            $byRound[$f['round']][] = $f;
        }
        ksort($byRound);

        $home = array_fill_keys($this->twentyClubs, 0);
        $away = array_fill_keys($this->twentyClubs, 0);

        foreach ($byRound as $round => $roundFixtures) {
            foreach ($roundFixtures as $f) {
                $home[$f['home_club_id']]++;
                $away[$f['away_club_id']]++;
            }

            // Assert balance at every round including the boundary rounds 19 and 20.
            foreach ($this->twentyClubs as $clubId) {
                $diff = abs($home[$clubId] - $away[$clubId]);
                $this->assertLessThanOrEqual(
                    2,
                    $diff,
                    "Club {$clubId} home/away balance |{$home[$clubId]}-{$away[$clubId]}|={$diff} "
                    . "exceeds 2 after round {$round} (including across the half-boundary)."
                );
            }
        }
    }

    #[Test]
    public function no_club_has_three_consecutive_home_or_away_matches_including_across_half_boundary(): void
    {
        $service  = new LeagueScheduleService(2025);
        $fixtures = $service->generateSchedule($this->twentyClubs);

        // Build per-club ordered list of 'H' or 'A' across all 38 rounds.
        $records = array_fill_keys($this->twentyClubs, []);

        $byRound = [];
        foreach ($fixtures as $f) {
            $byRound[$f['round']][] = $f;
        }
        ksort($byRound);

        foreach ($byRound as $roundFixtures) {
            foreach ($roundFixtures as $f) {
                $records[$f['home_club_id']][] = 'H';
                $records[$f['away_club_id']][] = 'A';
            }
        }

        // Verify globally across all 38 rounds — rounds 19 and 20 are consecutive entries
        // in the sequence, so the cross-boundary check is inherently included.
        foreach ($this->twentyClubs as $clubId) {
            $seq = $records[$clubId];
            $this->assertCount(38, $seq, "Club {$clubId} must appear in exactly 38 rounds.");

            for ($i = 2; $i < count($seq); $i++) {
                $this->assertFalse(
                    $seq[$i - 2] === $seq[$i - 1] && $seq[$i - 1] === $seq[$i] && $seq[$i] === 'H',
                    "Club {$clubId} has 3+ consecutive home matches ending at round " . ($i + 1) . " "
                    . "(this check spans the round-19 → round-20 boundary)."
                );
                $this->assertFalse(
                    $seq[$i - 2] === $seq[$i - 1] && $seq[$i - 1] === $seq[$i] && $seq[$i] === 'A',
                    "Club {$clubId} has 3+ consecutive away matches ending at round " . ($i + 1) . " "
                    . "(this check spans the round-19 → round-20 boundary)."
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    //  (i) Wall-clock generation time is below 1 second
    // -------------------------------------------------------------------------

    #[Test]
    public function schedule_generation_completes_within_one_second(): void
    {
        $service = new LeagueScheduleService(2025);

        $start   = hrtime(true); // monotonic nanosecond timer
        $fixtures = $service->generateSchedule($this->twentyClubs);
        $elapsed = hrtime(true) - $start;

        $elapsedSeconds = $elapsed / 1_000_000_000.0;

        $this->assertCount(380, $fixtures); // sanity check
        $this->assertLessThan(
            1.0,
            $elapsedSeconds,
            sprintf(
                'Schedule generation took %.4f seconds, which exceeds the 1-second budget.',
                $elapsedSeconds
            )
        );
    }

    // -------------------------------------------------------------------------
    //  Additional correctness assertions
    // -------------------------------------------------------------------------

    #[Test]
    public function round_1_starts_on_the_first_saturday_of_september(): void
    {
        $service  = new LeagueScheduleService(2025);
        $fixtures = $service->generateSchedule($this->twentyClubs);

        $round1Dates = array_map(
            static fn(array $f): string => $f['date']->format('Y-m-d'),
            array_filter($fixtures, static fn(array $f): bool => $f['round'] === 1)
        );

        $uniqueDates = array_unique($round1Dates);

        // All round-1 fixtures must fall on the weekend of 2025-09-06 (Saturday).
        // First Saturday of September 2025 is 2025-09-06.
        foreach ($uniqueDates as $dateStr) {
            $this->assertContains(
                $dateStr,
                ['2025-09-06', '2025-09-07'],
                "Round 1 fixtures must be on the first September 2025 weekend (Sep 6–7)."
            );
        }
    }

    #[Test]
    public function round_1_sunday_is_the_day_immediately_after_round_1_saturday(): void
    {
        $service  = new LeagueScheduleService(2025);
        $fixtures = $service->generateSchedule($this->twentyClubs);

        $round1 = array_filter($fixtures, static fn(array $f): bool => $f['round'] === 1);

        $saturdays = [];
        $sundays   = [];

        foreach ($round1 as $f) {
            $dow = (int) $f['date']->format('N');
            if ($dow === 6) {
                $saturdays[] = $f['date']->format('Y-m-d');
            } else {
                $sundays[] = $f['date']->format('Y-m-d');
            }
        }

        $this->assertNotEmpty($saturdays, 'Round 1 must have Saturday fixtures.');
        $this->assertNotEmpty($sundays,   'Round 1 must have Sunday fixtures.');

        $satDate = new \DateTimeImmutable(array_unique($saturdays)[0]);
        $sunDate = new \DateTimeImmutable(array_unique($sundays)[0]);

        // Sunday must be exactly Saturday + 1 day (not "first Sunday of September").
        $expectedSunday = $satDate->modify('+1 day')->format('Y-m-d');
        $this->assertSame(
            $expectedSunday,
            $sunDate->format('Y-m-d'),
            'Round 1 Sunday must be the calendar day immediately after round 1 Saturday.'
        );
    }

    #[Test]
    public function schedule_is_deterministic_for_same_inputs(): void
    {
        $service1 = new LeagueScheduleService(2025);
        $service2 = new LeagueScheduleService(2025);

        $schedule1 = $service1->generateSchedule($this->twentyClubs);
        $schedule2 = $service2->generateSchedule($this->twentyClubs);

        $this->assertSame($schedule1[0]['round'],        $schedule2[0]['round']);
        $this->assertSame($schedule1[0]['home_club_id'], $schedule2[0]['home_club_id']);
        $this->assertSame($schedule1[0]['away_club_id'], $schedule2[0]['away_club_id']);
        $this->assertSame(
            $schedule1[0]['date']->format('Y-m-d'),
            $schedule2[0]['date']->format('Y-m-d')
        );
    }

    #[Test]
    public function calling_generate_schedule_twice_on_same_instance_returns_identical_data(): void
    {
        $service = new LeagueScheduleService(2025);

        $schedule1 = $service->generateSchedule($this->twentyClubs);
        $schedule2 = $service->generateSchedule($this->twentyClubs);

        $this->assertCount(380, $schedule1);
        $this->assertCount(380, $schedule2);

        foreach (range(0, 379) as $i) {
            $this->assertSame(
                $schedule1[$i]['round'],
                $schedule2[$i]['round'],
                "Fixture {$i}: round mismatch between two calls on the same instance."
            );
            $this->assertSame(
                $schedule1[$i]['home_club_id'],
                $schedule2[$i]['home_club_id'],
                "Fixture {$i}: home_club_id mismatch between two calls on the same instance."
            );
            $this->assertSame(
                $schedule1[$i]['away_club_id'],
                $schedule2[$i]['away_club_id'],
                "Fixture {$i}: away_club_id mismatch between two calls on the same instance."
            );
            $this->assertSame(
                $schedule1[$i]['date']->format('Y-m-d'),
                $schedule2[$i]['date']->format('Y-m-d'),
                "Fixture {$i}: date mismatch between two calls on the same instance."
            );
        }
    }

    // -------------------------------------------------------------------------
    //  Input validation
    // -------------------------------------------------------------------------

    #[Test]
    public function it_throws_when_an_element_is_not_an_integer(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $clubs    = range(1, 19);
        $clubs[]  = '20'; // string instead of int

        $service = new LeagueScheduleService(2025);
        $service->generateSchedule($clubs);
    }

    #[Test]
    public function it_throws_when_a_float_is_provided_as_club_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $clubs   = range(1, 19);
        $clubs[] = 20.0; // float instead of int

        $service = new LeagueScheduleService(2025);
        $service->generateSchedule($clubs);
    }

    #[Test]
    public function type_check_takes_precedence_over_cardinality_check(): void
    {
        // 21 elements but one is a non-integer; type error must be reported, not count error.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/integer/i');

        $clubs   = range(1, 20);
        $clubs[] = '21'; // 21 elements, one non-integer

        $service = new LeagueScheduleService(2025);
        $service->generateSchedule($clubs);
    }

    #[Test]
    public function cardinality_check_takes_precedence_over_distinctness_check(): void
    {
        // 19 elements with a duplicate; cardinality error must be reported, not duplicate error.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/19|fewer|only/i');

        $clubs    = range(1, 18);
        $clubs[]  = 1; // duplicate
        // 19 elements total (18 unique + 1 duplicate)

        $service = new LeagueScheduleService(2025);
        $service->generateSchedule($clubs);
    }

    #[Test]
    public function it_throws_when_fewer_than_20_clubs_are_provided(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $service = new LeagueScheduleService(2025);
        $service->generateSchedule(range(1, 19));
    }

    #[Test]
    public function it_throws_when_more_than_20_clubs_are_provided(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $service = new LeagueScheduleService(2025);
        $service->generateSchedule(range(1, 21));
    }

    #[Test]
    public function it_throws_when_duplicate_clubs_are_provided(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $clubs = range(1, 19);
        $clubs[] = 1; // duplicate

        $service = new LeagueScheduleService(2025);
        $service->generateSchedule($clubs);
    }

    #[Test]
    public function caller_array_is_not_mutated_after_generate_schedule(): void
    {
        $original = range(1, 20);
        $input    = range(1, 20);

        $service = new LeagueScheduleService(2025);
        $service->generateSchedule($input);

        $this->assertSame(
            $original,
            $input,
            'The caller\'s array must remain identical (same elements, same order, same keys) after generateSchedule returns.'
        );
    }

    #[Test]
    public function caller_array_is_not_mutated_when_validation_throws(): void
    {
        $input = range(1, 19); // triggers cardinality exception
        $snapshot = $input;

        try {
            $service = new LeagueScheduleService(2025);
            $service->generateSchedule($input);
        } catch (InvalidArgumentException) {
            // expected
        }

        $this->assertSame(
            $snapshot,
            $input,
            'The caller\'s array must not be mutated even when an exception is thrown.'
        );
    }

    #[Test]
    public function it_throws_when_season_year_is_below_minimum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LeagueScheduleService(1899);
    }

    #[Test]
    public function it_throws_when_season_year_is_above_maximum(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new LeagueScheduleService(2101);
    }

    #[Test]
    public function it_accepts_boundary_years_1900_and_2100(): void
    {
        $s1 = new LeagueScheduleService(1900);
        $this->assertCount(380, $s1->generateSchedule($this->twentyClubs));

        $s2 = new LeagueScheduleService(2100);
        $this->assertCount(380, $s2->generateSchedule($this->twentyClubs));
    }

    #[Test]
    public function it_uses_current_year_when_no_year_is_supplied(): void
    {
        $service  = new LeagueScheduleService();
        $fixtures = $service->generateSchedule($this->twentyClubs);

        // Evaluate current year in UTC, matching the service's own resolution.
        $currentYear = (int) (new DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y');

        // The first round must start in September of the current year.
        $round1 = array_filter($fixtures, static fn(array $f): bool => $f['round'] === 1);
        $firstFixture = reset($round1);

        $this->assertSame(
            $currentYear,
            (int) $firstFixture['date']->format('Y')
        );

        $this->assertSame(
            9,
            (int) $firstFixture['date']->format('n')
        );
    }

    // -------------------------------------------------------------------------
    //  Home/away balance constraints (comprehensive)
    // -------------------------------------------------------------------------

    #[Test]
    public function home_away_difference_never_exceeds_2_after_any_round(): void
    {
        $service  = new LeagueScheduleService(2025);
        $fixtures = $service->generateSchedule($this->twentyClubs);

        // Group by round, sorted.
        $byRound = [];
        foreach ($fixtures as $f) {
            $byRound[$f['round']][] = $f;
        }
        ksort($byRound);

        // Running home/away counters per club.
        $home = array_fill_keys($this->twentyClubs, 0);
        $away = array_fill_keys($this->twentyClubs, 0);

        foreach ($byRound as $round => $roundFixtures) {
            foreach ($roundFixtures as $f) {
                $home[$f['home_club_id']]++;
                $away[$f['away_club_id']]++;
            }

            foreach ($this->twentyClubs as $clubId) {
                $diff = abs($home[$clubId] - $away[$clubId]);
                $this->assertLessThanOrEqual(
                    2,
                    $diff,
                    "Club {$clubId} home/away difference exceeds 2 after round {$round} "
                    . "(home={$home[$clubId]}, away={$away[$clubId]})."
                );
            }
        }
    }

    #[Test]
    public function no_club_has_more_than_two_consecutive_home_or_away_matches(): void
    {
        $service  = new LeagueScheduleService(2025);
        $fixtures = $service->generateSchedule($this->twentyClubs);

        // Build per-club ordered list of 'H' or 'A'.
        $records = array_fill_keys($this->twentyClubs, []);

        $byRound = [];
        foreach ($fixtures as $f) {
            $byRound[$f['round']][] = $f;
        }
        ksort($byRound);

        foreach ($byRound as $roundFixtures) {
            foreach ($roundFixtures as $f) {
                $records[$f['home_club_id']][] = 'H';
                $records[$f['away_club_id']][]  = 'A';
            }
        }

        foreach ($this->twentyClubs as $clubId) {
            $seq = $records[$clubId];
            for ($i = 2; $i < count($seq); $i++) {
                $this->assertFalse(
                    $seq[$i - 2] === $seq[$i - 1] && $seq[$i - 1] === $seq[$i] && $seq[$i] === 'H',
                    "Club {$clubId} has 3+ consecutive home matches ending at round " . ($i + 1) . "."
                );
                $this->assertFalse(
                    $seq[$i - 2] === $seq[$i - 1] && $seq[$i - 1] === $seq[$i] && $seq[$i] === 'A',
                    "Club {$clubId} has 3+ consecutive away matches ending at round " . ($i + 1) . "."
                );
            }
        }
    }
}
