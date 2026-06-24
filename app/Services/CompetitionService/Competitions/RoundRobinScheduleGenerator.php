<?php

namespace App\Services\CompetitionService\Competitions;

use InvalidArgumentException;

class RoundRobinScheduleGenerator
{
    private const MIN_CLUB_COUNT = 4;
    private const MAX_CLUB_COUNT = 20;

    /**
     * @param int[] $clubIds
     *
     * @return array<int, list<array{int, int}>> Rounds with [homeClubId, awayClubId] pairs.
     */
    public function generateDoubleRoundRobinRounds(array $clubIds): array
    {
        $this->validateClubs($clubIds);

        $clubs = array_values($clubIds);
        $firstHalf = $this->assignHomeAway($this->circleMethodPairings($clubs));

        $secondHalf = array_reverse(
            array_map(
                static fn(array $round): array => array_map(
                    static fn(array $pair): array => [$pair[1], $pair[0]],
                    $round
                ),
                $firstHalf
            )
        );

        return array_merge($firstHalf, $secondHalf);
    }

    /** @param mixed[] $clubIds */
    private function validateClubs(array $clubIds): void
    {
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

        $count = count($clubIds);

        if ($count < self::MIN_CLUB_COUNT) {
            throw new InvalidArgumentException(
                sprintf('Expected at least %d clubs, but only %d were provided.', self::MIN_CLUB_COUNT, $count)
            );
        }

        if ($count > self::MAX_CLUB_COUNT) {
            throw new InvalidArgumentException(
                sprintf('Expected at most %d clubs, but %d were provided.', self::MAX_CLUB_COUNT, $count)
            );
        }

        if ($count % 2 !== 0) {
            throw new InvalidArgumentException('Expected an even number of clubs.');
        }

        if (count(array_unique($clubIds)) !== $count) {
            throw new InvalidArgumentException('Duplicate club identifiers are not allowed.');
        }
    }

    /**
     * @param int[] $clubs
     *
     * @return array<int, list<array{int, int}>>
     */
    private function circleMethodPairings(array $clubs): array
    {
        $clubCount = count($clubs);
        $half = (int) ($clubCount / 2);
        $fixed = $clubs[0];
        $rotating = array_slice($clubs, 1);
        $rounds = [];

        for ($round = 0; $round < $clubCount - 1; $round++) {
            $pairs = [[$fixed, $rotating[0]]];

            for ($i = 1; $i < $half; $i++) {
                $pairs[] = [$rotating[$i], $rotating[$clubCount - 1 - $i]];
            }

            $rounds[] = $pairs;
            $rotating = array_merge(array_slice($rotating, 1), [array_shift($rotating)]);
        }

        return $rounds;
    }

    /**
     * @param array<int, list<array{int, int}>> $rawPairings
     *
     * @return array<int, list<array{int, int}>>
     */
    private function assignHomeAway(array $rawPairings): array
    {
        $allClubs = [];
        foreach ($rawPairings[0] as [$a, $b]) {
            $allClubs[] = $a;
            $allClubs[] = $b;
        }

        $initState = [
            'balance' => array_fill_keys($allClubs, 0),
            'streak' => array_fill_keys($allClubs, 0),
            'lastRole' => array_fill_keys($allClubs, null),
        ];

        $result = array_fill(0, count($rawPairings), []);

        if (!$this->backtrack(0, 0, $rawPairings, $initState, $result)) {
            throw new InvalidArgumentException('Unable to generate a valid round-robin schedule for the provided clubs.');
        }

        return $result;
    }

    /**
     * @param array<int, list<array{int, int}>> $pairings
     * @param array{balance: array<int,int>, streak: array<int,int>, lastRole: array<int,string|null>} $state
     * @param array<int, list<array{int, int}>> $result
     */
    private function backtrack(int $round, int $pairIdx, array $pairings, array $state, array &$result): bool
    {
        if ($round === count($pairings)) {
            return true;
        }

        $pairs = $pairings[$round];

        if ($pairIdx === count($pairs)) {
            return $this->backtrack($round + 1, 0, $pairings, $state, $result);
        }

        [$a, $b] = $pairs[$pairIdx];

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

    /** @param array{balance: array<int,int>, streak: array<int,int>, lastRole: array<int,string|null>} $state */
    private function isAssignmentFeasible(int $home, int $away, array $state): bool
    {
        if ($state['balance'][$home] >= 2) {
            return false;
        }

        if ($state['streak'][$home] >= 2 && $state['lastRole'][$home] === 'H') {
            return false;
        }

        if ($state['balance'][$away] <= -2) {
            return false;
        }

        if ($state['streak'][$away] >= 2 && $state['lastRole'][$away] === 'A') {
            return false;
        }

        return true;
    }

    /**
     * @param array{balance: array<int,int>, streak: array<int,int>, lastRole: array<int,string|null>} $state
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
            $state['streak'][$home] = 1;
            $state['lastRole'][$home] = 'H';
        }

        if ($state['lastRole'][$away] === 'A') {
            $state['streak'][$away]++;
        } else {
            $state['streak'][$away] = 1;
            $state['lastRole'][$away] = 'A';
        }

        return $state;
    }
}
