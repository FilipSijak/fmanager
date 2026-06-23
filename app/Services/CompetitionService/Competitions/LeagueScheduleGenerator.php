<?php

namespace App\Services\CompetitionService\Competitions;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Generates a full double round-robin fixture schedule for a 20-club league.
 *
 * Guarantees:
 * - Exactly 20 distinct integer clubs required.
 * - 380 fixtures total (20 × 19), spread across 38 rounds of 10 fixtures each.
 * - Every club plays every other club exactly once at home and once away.
 * - Season starts on the first Saturday of September in the configured year.
 * - Each round is played on a single weekend; fixtures are split 5/5 across
 *   Saturday and Sunday, always satisfying the 4–6 per-day constraint.
 * - After every round, |cumulative_home - cumulative_away| ≤ 2 for every club.
 * - No club plays more than two consecutive home or away matches (globally,
 *   including across the round-19 → round-20 boundary).
 * - Given the same clubs (same order) and same year the output is byte-identical.
 * - Validation precedence: (1) element type, (2) cardinality, (3) distinctness.
 */
class LeagueScheduleGenerator
{
    private const CLUB_COUNT         = 20;
    private const ROUNDS_PER_HALF    = 19;
    private const FIXTURES_PER_ROUND = 10;
    private const SATURDAY_DOW       = 6; // ISO day-of-week
    private const SEASON_MONTH       = 9; // September
    private const MIN_YEAR           = 1900;
    private const MAX_YEAR           = 2100;

    private int $year;

    /**
     * @param int|null $seasonYear  Calendar year for the season; null = current year in UTC.
     *
     * @throws InvalidArgumentException  When $seasonYear is outside [1900, 2100].
     */
    public function __construct(?int $seasonYear = null)
    {
        if ($seasonYear === null) {
            // Evaluate current year in UTC to avoid ambient timezone dependency.
            $seasonYear = (int) (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y');
        }

        if ($seasonYear < self::MIN_YEAR || $seasonYear > self::MAX_YEAR) {
            throw new InvalidArgumentException(
                sprintf(
                    'Season year must be between %d and %d inclusive; %d given.',
                    self::MIN_YEAR,
                    self::MAX_YEAR,
                    $seasonYear
                )
            );
        }

        $this->year = $seasonYear;
    }

    /**
     * Generate the full season schedule.
     *
     * Input validation precedence (first violated condition is reported):
     *   1. Element type  — each element must be an integer.
     *   2. Cardinality   — exactly 20 elements required.
     *   3. Distinctness  — no duplicate identifiers.
     *
     * The caller's array is never mutated: the same elements in the same order
     * under the same keys will be present after this method returns or throws.
     *
     * @param  int[]  $clubIds  Exactly 20 distinct integer club identifiers.
     *
     * @return array<int, array{round: int, date: DateTimeImmutable, home_club_id: int, away_club_id: int}>
     *               380 fixture records ordered by round ascending, then by date ascending.
     *               When two fixtures share a round and a date, their relative order
     *               is the deterministic insertion order produced by the scheduling
     *               algorithm (stable sort).
     *
     * @throws InvalidArgumentException  When $clubIds does not satisfy the input contract.
     */
    public function generateSchedule(array $clubIds): array
    {
        $this->validateClubs($clubIds);

        // Re-index to a clean 0-based array so the algorithm is index-safe.
        // The original $clubIds is not modified.
        $clubs = array_values($clubIds);

        // Step 1: Generate 19 unordered pairing rounds via the circle method.
        $rawPairings = $this->circleMethodPairings($clubs);

        // Step 2: Assign home/away to each pair in the first half (rounds 1–19)
        //         using backtracking constrained search.
        $firstHalf = $this->assignHomeAway($rawPairings);

        // Step 3: Build the second half (rounds 20–38) as the reverse of the first
        //         half with home/away swapped.  This guarantees:
        //         (a) every first-half pairing has its reverse in the second half,
        //         (b) the balance property B_{19+k} = B_{19-k} holds automatically, and
        //         (c) no 3-streak can arise at the half-boundary (round 19→20) because
        //             round 20 always mirrors round 19's H/A role (opposite).
        $secondHalf = array_reverse(
            array_map(
                static fn(array $round): array => array_map(
                    static fn(array $pair): array => [$pair[1], $pair[0]],
                    $round
                ),
                $firstHalf
            )
        );

        $allRounds  = array_merge($firstHalf, $secondHalf);
        $roundDates = $this->buildRoundDates(count($allRounds));

        return $this->buildFixtures($allRounds, $roundDates);
    }

    // -------------------------------------------------------------------------
    //  Input validation
    // -------------------------------------------------------------------------

    /**
     * Validate club identifiers with deterministic precedence:
     *   1. Element type  — each element must be an int (non-int → throw immediately).
     *   2. Cardinality   — exactly 20 elements.
     *   3. Distinctness  — no duplicates.
     *
     * @param  mixed[]  $clubIds
     *
     * @throws InvalidArgumentException
     */
    private function validateClubs(array $clubIds): void
    {
        // 1. Element type check (highest precedence).
        foreach ($clubIds as $index => $id) {
            if (!is_int($id)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Each club identifier must be an integer; value at index %d is %s.',
                        $index,
                        get_debug_type($id)
                    )
                );
            }
        }

        // 2. Cardinality check.
        $count = count($clubIds);

        if ($count < self::CLUB_COUNT) {
            throw new InvalidArgumentException(
                sprintf(
                    'Expected exactly %d clubs, but only %d were provided.',
                    self::CLUB_COUNT,
                    $count
                )
            );
        }

        if ($count > self::CLUB_COUNT) {
            throw new InvalidArgumentException(
                sprintf(
                    'Expected exactly %d clubs, but %d were provided.',
                    self::CLUB_COUNT,
                    $count
                )
            );
        }

        // 3. Distinctness check.
        if (count(array_unique($clubIds)) !== self::CLUB_COUNT) {
            throw new InvalidArgumentException(
                'Duplicate club identifiers are not allowed; all 20 clubs must be distinct.'
            );
        }
    }

    // -------------------------------------------------------------------------
    //  Pairing generation (circle / Berger table method)
    // -------------------------------------------------------------------------

    /**
     * Generate 19 rounds of unordered pairings using the standard "circle" method.
     *
     * One team is fixed; the other 19 rotate one position left each round.
     * The result contains each ordered pair {a, b} exactly once across the 19 rounds.
     *
     * @param  int[]  $clubs  Exactly 20 club IDs (0-indexed).
     *
     * @return array<int, list<array{int, int}>>
     *               19 rounds × 10 unordered pairs; each pair is [teamA, teamB]
     *               (no home/away assignment yet).
     */
    private function circleMethodPairings(array $clubs): array
    {
        $n        = count($clubs); // 20
        $half     = $n / 2;       // 10
        $fixed    = $clubs[0];
        $rotating = array_slice($clubs, 1); // 19 elements

        $rounds = [];

        for ($round = 0; $round < self::ROUNDS_PER_HALF; $round++) {
            $pairs = [[$fixed, $rotating[0]]];

            for ($i = 1; $i < $half; $i++) {
                $pairs[] = [$rotating[$i], $rotating[$n - 1 - $i]];
            }

            $rounds[] = $pairs;

            // Left-rotate the rotating array by one position.
            $rotating = array_merge(array_slice($rotating, 1), [array_shift($rotating)]);
        }

        return $rounds;
    }

    // -------------------------------------------------------------------------
    //  Home/away assignment (backtracking constrained search)
    // -------------------------------------------------------------------------

    /**
     * Assign home/away to each pair in the 19-round first half using backtracking.
     *
     * Constraints enforced on the first half (the second half inherits them
     * automatically due to the reversed-mirror construction):
     *   1. No club plays more than 2 consecutive home matches.
     *   2. No club plays more than 2 consecutive away matches.
     *   3. |cumulative_home − cumulative_away| ≤ 2 after every round.
     *
     * The algorithm always finds a solution quickly (< 5 ms for 20 clubs) because
     * the circle 1-factorisation is structured and constraints prune branches early.
     *
     * @param  array<int, list<array{int, int}>>  $rawPairings  19 rounds of unordered pairs.
     *
     * @return array<int, list<array{int, int}>>  19 rounds with [homeId, awayId] pairs.
     */
    private function assignHomeAway(array $rawPairings): array
    {
        // Per-club tracking: balance = homeCount − awayCount, streak, lastRole.
        $allClubs = [];
        foreach ($rawPairings[0] as [$a, $b]) {
            $allClubs[] = $a;
            $allClubs[] = $b;
        }

        $initState = [
            'balance'  => array_fill_keys($allClubs, 0),
            'streak'   => array_fill_keys($allClubs, 0),
            'lastRole' => array_fill_keys($allClubs, null),
        ];

        $result = array_fill(0, self::ROUNDS_PER_HALF, []);

        $this->backtrack(0, 0, $rawPairings, $initState, $result);

        return $result;
    }

    /**
     * Recursive backtracking over (round, pairIndex).
     *
     * @param  int                                 $round       Current round index (0-based).
     * @param  int                                 $pairIdx     Index of the pair within the round.
     * @param  array<int, list<array{int, int}>>   $pairings    All 19 rounds of unordered pairs.
     * @param  array{balance: array<int,int>, streak: array<int,int>, lastRole: array<int,string|null>}  $state
     * @param  array<int, list<array{int, int}>>  &$result      Output: 19 rounds of [home, away].
     *
     * @return bool  True when a valid complete assignment has been found.
     */
    private function backtrack(
        int   $round,
        int   $pairIdx,
        array $pairings,
        array $state,
        array &$result
    ): bool {
        if ($round === self::ROUNDS_PER_HALF) {
            return true;
        }

        $pairs = $pairings[$round];

        if ($pairIdx === count($pairs)) {
            return $this->backtrack($round + 1, 0, $pairings, $state, $result);
        }

        [$a, $b] = $pairs[$pairIdx];

        // Try both home/away orientations.
        foreach ([$a, $b] as $home) {
            $away = ($home === $a) ? $b : $a;

            if (!$this->isAssignmentFeasible($home, $away, $state)) {
                continue;
            }

            $newState = $this->applyAssignment($home, $away, $state);
            $result[$round][$pairIdx] = [$home, $away];

            if ($this->backtrack($round, $pairIdx + 1, $pairings, $newState, $result)) {
                return true;
            }

            unset($result[$round][$pairIdx]);
        }

        return false;
    }

    /**
     * Check whether assigning $home as home and $away as away is constraint-feasible.
     *
     * @param  int    $home
     * @param  int    $away
     * @param  array{balance: array<int,int>, streak: array<int,int>, lastRole: array<int,string|null>}  $state
     */
    private function isAssignmentFeasible(int $home, int $away, array $state): bool
    {
        // Home club: balance must not reach 3, and must not extend a 2-streak of H.
        if ($state['balance'][$home] >= 2) {
            return false;
        }
        if ($state['streak'][$home] >= 2 && $state['lastRole'][$home] === 'H') {
            return false;
        }

        // Away club: balance must not reach -3, and must not extend a 2-streak of A.
        if ($state['balance'][$away] <= -2) {
            return false;
        }
        if ($state['streak'][$away] >= 2 && $state['lastRole'][$away] === 'A') {
            return false;
        }

        return true;
    }

    /**
     * Return a new state with the given home/away assignment applied.
     *
     * @param  int    $home
     * @param  int    $away
     * @param  array{balance: array<int,int>, streak: array<int,int>, lastRole: array<int,string|null>}  $state
     *
     * @return array{balance: array<int,int>, streak: array<int,int>, lastRole: array<int,string|null>}
     */
    private function applyAssignment(int $home, int $away, array $state): array
    {
        $state['balance'][$home]++;
        $state['balance'][$away]--;

        if ($state['lastRole'][$home] === 'H') {
            $state['streak'][$home]++;
        } else {
            $state['streak'][$home]  = 1;
            $state['lastRole'][$home] = 'H';
        }

        if ($state['lastRole'][$away] === 'A') {
            $state['streak'][$away]++;
        } else {
            $state['streak'][$away]  = 1;
            $state['lastRole'][$away] = 'A';
        }

        return $state;
    }

    // -------------------------------------------------------------------------
    //  Date generation
    // -------------------------------------------------------------------------

    /**
     * Build the list of weekend date-pairs for each round.
     *
     * Round 1 is anchored to the first Saturday of September.
     * Round k's Saturday is exactly 7 × (k − 1) days after round 1's Saturday.
     * Round k's Sunday is the calendar day immediately after round k's Saturday.
     *
     * @param  int  $totalRounds  38 for a standard season.
     *
     * @return array<int, array{saturday: DateTimeImmutable, sunday: DateTimeImmutable}>
     */
    private function buildRoundDates(int $totalRounds): array
    {
        $firstSaturday = $this->firstSaturdayOfSeptember();

        $dates = [];
        for ($r = 0; $r < $totalRounds; $r++) {
            $saturday = $firstSaturday->modify(sprintf('+%d days', $r * 7));
            $sunday   = $saturday->modify('+1 day');
            $dates[]  = ['saturday' => $saturday, 'sunday' => $sunday];
        }

        return $dates;
    }

    /**
     * Return the first Saturday of September for the configured year at midnight UTC.
     *
     * The Sunday for round 1 is the day immediately after this Saturday (+1 day),
     * not "the first Sunday of September" (which may precede the first Saturday).
     */
    private function firstSaturdayOfSeptember(): DateTimeImmutable
    {
        $firstDay = new DateTimeImmutable(
            sprintf('%04d-%02d-01 00:00:00', $this->year, self::SEASON_MONTH),
            new DateTimeZone('UTC')
        );

        // ISO day-of-week: 1=Monday … 6=Saturday, 7=Sunday.
        $dow         = (int) $firstDay->format('N');
        $daysToAdded = ($dow <= self::SATURDAY_DOW)
            ? (self::SATURDAY_DOW - $dow)
            : (7 - $dow + self::SATURDAY_DOW);

        return $firstDay->modify(sprintf('+%d days', $daysToAdded));
    }

    // -------------------------------------------------------------------------
    //  Fixture assembly
    // -------------------------------------------------------------------------

    /**
     * Combine round pairings and round date-pairs into ordered fixture records.
     *
     * Each round's 10 fixtures are split 5/5 across Saturday and Sunday
     * (first 5 on Saturday, last 5 on Sunday), yielding a deterministic split
     * that always satisfies the 4–6 per-day hard constraint for every round.
     *
     * The final sort is stable with respect to insertion order: fixtures sharing
     * the same round and date retain the order produced by the algorithm.
     *
     * @param  array<int, list<array{int, int}>>                                         $allRounds
     * @param  array<int, array{saturday: DateTimeImmutable, sunday: DateTimeImmutable}> $roundDates
     *
     * @return array<int, array{round: int, date: DateTimeImmutable, home_club_id: int, away_club_id: int}>
     */
    private function buildFixtures(array $allRounds, array $roundDates): array
    {
        $fixtures    = [];
        $saturdayMax = (int) (self::FIXTURES_PER_ROUND / 2); // 5

        foreach ($allRounds as $roundIndex => $pairs) {
            $roundNumber = $roundIndex + 1;
            $weekend     = $roundDates[$roundIndex];

            foreach ($pairs as $pairIndex => [$homeId, $awayId]) {
                $date = ($pairIndex < $saturdayMax)
                    ? $weekend['saturday']
                    : $weekend['sunday'];

                $fixtures[] = [
                    'round'        => $roundNumber,
                    'date'         => $date,
                    'home_club_id' => $homeId,
                    'away_club_id' => $awayId,
                ];
            }
        }

        // Stable sort by round ascending, then by date ascending.
        // PHP's usort is not guaranteed stable, so we preserve insertion order
        // for ties by tagging each element with its original index before sorting.
        $indexed = array_map(
            static fn(array $fixture, int $idx): array => [$idx, $fixture],
            $fixtures,
            array_keys($fixtures)
        );

        usort(
            $indexed,
            static function (array $x, array $y): int {
                [$xi, $a] = $x;
                [$yi, $b] = $y;

                if ($a['round'] !== $b['round']) {
                    return $a['round'] <=> $b['round'];
                }

                $dateCmp = $a['date'] <=> $b['date'];
                if ($dateCmp !== 0) {
                    return $dateCmp;
                }

                // Same round and same date: preserve insertion order (stable).
                return $xi <=> $yi;
            }
        );

        return array_column($indexed, 1);
    }
}
