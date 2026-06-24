<?php

namespace App\Services\CompetitionService\Competitions;

use DateTimeImmutable;
use InvalidArgumentException;

class GroupStageScheduleGenerator
{
    private const CLUBS_PER_GROUP = 4;
    private const TUESDAY_DOW = 2;

    public function __construct(private readonly RoundRobinScheduleGenerator $roundRobinScheduleGenerator = new RoundRobinScheduleGenerator())
    {
    }

    /**
     * @param array<int, int[]> $groups
     *
     * @return array<int, array{round: int, group_id: int, date: DateTimeImmutable, home_club_id: int, away_club_id: int}>
     */
    public function generate(array $groups, DateTimeImmutable $startDate): array
    {
        $fixtures = [];
        $roundDates = $this->buildRoundDates($startDate, 6);

        foreach ($groups as $groupId => $clubIds) {
            $this->validateGroup($groupId, $clubIds);
            $rounds = $this->roundRobinScheduleGenerator->generateDoubleRoundRobinRounds(array_values($clubIds));

            foreach ($rounds as $roundIndex => $pairs) {
                $roundNumber = $roundIndex + 1;
                $datePair = $roundDates[$roundIndex];

                foreach ($pairs as $pairIndex => [$homeId, $awayId]) {
                    $fixtures[] = [
                        'round' => $roundNumber,
                        'group_id' => (int) $groupId,
                        'date' => $pairIndex === 0 ? $datePair['tuesday'] : $datePair['wednesday'],
                        'home_club_id' => $homeId,
                        'away_club_id' => $awayId,
                    ];
                }
            }
        }

        return $fixtures;
    }

    /** @param mixed[] $clubIds */
    private function validateGroup(int|string $groupId, array $clubIds): void
    {
        if (count($clubIds) !== self::CLUBS_PER_GROUP) {
            throw new InvalidArgumentException(
                sprintf('Tournament group %s must contain exactly %d clubs.', $groupId, self::CLUBS_PER_GROUP)
            );
        }
    }

    /**
     * @return array<int, array{tuesday: DateTimeImmutable, wednesday: DateTimeImmutable}>
     */
    private function buildRoundDates(DateTimeImmutable $startDate, int $roundCount): array
    {
        $firstTuesday = $this->nextTuesday($startDate);
        $dates = [];

        for ($round = 0; $round < $roundCount; $round++) {
            $tuesday = $firstTuesday->modify(sprintf('+%d days', $round * 7));
            $dates[] = [
                'tuesday' => $tuesday,
                'wednesday' => $tuesday->modify('+1 day'),
            ];
        }

        return $dates;
    }

    private function nextTuesday(DateTimeImmutable $date): DateTimeImmutable
    {
        $dow = (int) $date->format('N');
        $daysToAdd = ($dow <= self::TUESDAY_DOW)
            ? (self::TUESDAY_DOW - $dow)
            : (7 - $dow + self::TUESDAY_DOW);

        return $date->modify(sprintf('+%d days', $daysToAdd));
    }
}
