<?php

namespace App\Services\CompetitionService\Competitions;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Generates a full double round-robin fixture schedule for a 20-club league.
 */
class LeagueScheduleGenerator
{
    private const CLUB_COUNT = 20;
    private const FIXTURES_PER_ROUND = 10;
    private const SATURDAY_DOW = 6;
    private const SEASON_MONTH = 9;
    private const MIN_YEAR = 1900;
    private const MAX_YEAR = 2100;

    private int $year;

    public function __construct(?int $seasonYear = null)
    {
        if ($seasonYear === null) {
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
     * @param int[] $clubIds Exactly 20 distinct integer club identifiers.
     *
     * @return array<int, array{round: int, date: DateTimeImmutable, home_club_id: int, away_club_id: int}>
     */
    public function generateSchedule(array $clubIds): array
    {
        $this->validateClubs($clubIds);

        $rounds = (new RoundRobinScheduleGenerator())->generateDoubleRoundRobinRounds($clubIds);
        $roundDates = $this->buildRoundDates(count($rounds));

        return $this->buildFixtures($rounds, $roundDates);
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

        if ($count < self::CLUB_COUNT) {
            throw new InvalidArgumentException(
                sprintf('Expected exactly %d clubs, but only %d were provided.', self::CLUB_COUNT, $count)
            );
        }

        if ($count > self::CLUB_COUNT) {
            throw new InvalidArgumentException(
                sprintf('Expected exactly %d clubs, but %d were provided.', self::CLUB_COUNT, $count)
            );
        }

        if (count(array_unique($clubIds)) !== self::CLUB_COUNT) {
            throw new InvalidArgumentException('Duplicate club identifiers are not allowed; all 20 clubs must be distinct.');
        }
    }

    /**
     * @return array<int, array{saturday: DateTimeImmutable, sunday: DateTimeImmutable}>
     */
    private function buildRoundDates(int $totalRounds): array
    {
        $firstSaturday = $this->firstSaturdayOfSeptember();
        $dates = [];

        for ($r = 0; $r < $totalRounds; $r++) {
            $saturday = $firstSaturday->modify(sprintf('+%d days', $r * 7));
            $dates[] = [
                'saturday' => $saturday,
                'sunday' => $saturday->modify('+1 day'),
            ];
        }

        return $dates;
    }

    private function firstSaturdayOfSeptember(): DateTimeImmutable
    {
        $firstDay = new DateTimeImmutable(
            sprintf('%04d-%02d-01 00:00:00', $this->year, self::SEASON_MONTH),
            new DateTimeZone('UTC')
        );

        $dow = (int) $firstDay->format('N');
        $daysToAdd = ($dow <= self::SATURDAY_DOW)
            ? (self::SATURDAY_DOW - $dow)
            : (7 - $dow + self::SATURDAY_DOW);

        return $firstDay->modify(sprintf('+%d days', $daysToAdd));
    }

    /**
     * @param array<int, list<array{int, int}>> $rounds
     * @param array<int, array{saturday: DateTimeImmutable, sunday: DateTimeImmutable}> $roundDates
     *
     * @return array<int, array{round: int, date: DateTimeImmutable, home_club_id: int, away_club_id: int}>
     */
    private function buildFixtures(array $rounds, array $roundDates): array
    {
        $fixtures = [];
        $saturdayMax = (int) (self::FIXTURES_PER_ROUND / 2);

        foreach ($rounds as $roundIndex => $pairs) {
            $roundNumber = $roundIndex + 1;
            $weekend = $roundDates[$roundIndex];

            foreach ($pairs as $pairIndex => [$homeId, $awayId]) {
                $fixtures[] = [
                    'round' => $roundNumber,
                    'date' => ($pairIndex < $saturdayMax) ? $weekend['saturday'] : $weekend['sunday'],
                    'home_club_id' => $homeId,
                    'away_club_id' => $awayId,
                ];
            }
        }

        return $this->stableSortFixtures($fixtures);
    }

    /**
     * @param array<int, array{round: int, date: DateTimeImmutable, home_club_id: int, away_club_id: int}> $fixtures
     *
     * @return array<int, array{round: int, date: DateTimeImmutable, home_club_id: int, away_club_id: int}>
     */
    private function stableSortFixtures(array $fixtures): array
    {
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

                return $dateCmp !== 0 ? $dateCmp : $xi <=> $yi;
            }
        );

        return array_column($indexed, 1);
    }
}
