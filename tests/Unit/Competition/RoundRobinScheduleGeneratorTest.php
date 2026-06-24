<?php

namespace Tests\Unit\Competition;

use App\Services\CompetitionService\Competitions\RoundRobinScheduleGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoundRobinScheduleGeneratorTest extends TestCase
{
    #[Test]
    public function it_generates_a_four_club_double_round_robin(): void
    {
        $rounds = (new RoundRobinScheduleGenerator())->generateDoubleRoundRobinRounds([1, 2, 3, 4]);

        $this->assertCount(6, $rounds);

        foreach ($rounds as $round) {
            $this->assertCount(2, $round);
            $this->assertSame([1, 2, 3, 4], $this->sortedClubsInRound($round));
        }

        $this->assertEveryPairAppearsHomeAndAway($rounds, 4);
    }

    #[Test]
    public function it_generates_a_twenty_club_double_round_robin(): void
    {
        $rounds = (new RoundRobinScheduleGenerator())->generateDoubleRoundRobinRounds(range(1, 20));

        $this->assertCount(38, $rounds);

        foreach ($rounds as $round) {
            $this->assertCount(10, $round);
            $this->assertSame(range(1, 20), $this->sortedClubsInRound($round));
        }

        $this->assertEveryPairAppearsHomeAndAway($rounds, 20);
    }

    #[Test]
    #[DataProvider('invalidClubSets')]
    public function it_rejects_invalid_club_sets(array $clubIds): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RoundRobinScheduleGenerator())->generateDoubleRoundRobinRounds($clubIds);
    }

    public static function invalidClubSets(): array
    {
        return [
            'non integer' => [[1, 2, 3, '4']],
            'too few' => [[1, 2]],
            'too many' => [range(1, 22)],
            'odd count' => [[1, 2, 3, 4, 5]],
            'duplicates' => [[1, 2, 3, 3]],
        ];
    }

    private function sortedClubsInRound(array $round): array
    {
        $clubs = [];

        foreach ($round as [$homeId, $awayId]) {
            $clubs[] = $homeId;
            $clubs[] = $awayId;
        }

        sort($clubs);

        return $clubs;
    }

    private function assertEveryPairAppearsHomeAndAway(array $rounds, int $clubCount): void
    {
        $fixtures = [];

        foreach ($rounds as $round) {
            foreach ($round as [$homeId, $awayId]) {
                $fixtures[$homeId.'->'.$awayId] = ($fixtures[$homeId.'->'.$awayId] ?? 0) + 1;
            }
        }

        $this->assertCount($clubCount * ($clubCount - 1), $fixtures);

        foreach ($fixtures as $directedPair => $count) {
            $this->assertSame(1, $count, $directedPair.' should appear exactly once.');
        }
    }
}
