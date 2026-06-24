<?php

namespace Tests\Unit\Competition;

use App\Services\CompetitionService\Competitions\GroupStageScheduleGenerator;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GroupStageScheduleGeneratorTest extends TestCase
{
    #[Test]
    public function it_generates_double_round_robin_fixtures_for_each_group_on_tuesday_and_wednesday(): void
    {
        $fixtures = (new GroupStageScheduleGenerator())->generate(
            [
                1 => [1, 2, 3, 4],
                2 => [5, 6, 7, 8],
            ],
            new DateTimeImmutable('2026-08-15')
        );

        $this->assertCount(24, $fixtures);
        $this->assertSame([1, 2, 3, 4, 5, 6], array_values(array_unique(array_column($fixtures, 'round'))));
        $this->assertSame([1, 2], array_values(array_unique(array_column($fixtures, 'group_id'))));

        foreach ($fixtures as $fixture) {
            $this->assertContains((int) $fixture['date']->format('N'), [2, 3]);
        }

        $this->assertEveryPairAppearsHomeAndAway($fixtures, [1, 2, 3, 4], 1);
        $this->assertEveryPairAppearsHomeAndAway($fixtures, [5, 6, 7, 8], 2);
    }

    #[Test]
    public function it_rejects_groups_that_do_not_have_four_clubs(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must contain exactly 4 clubs');

        (new GroupStageScheduleGenerator())->generate([1 => [1, 2, 3]], new DateTimeImmutable('2026-08-15'));
    }

    private function assertEveryPairAppearsHomeAndAway(array $fixtures, array $clubIds, int $groupId): void
    {
        $directedPairs = [];
        $clubLookup = array_flip($clubIds);

        foreach ($fixtures as $fixture) {
            if ($fixture['group_id'] !== $groupId) {
                continue;
            }

            $this->assertArrayHasKey($fixture['home_club_id'], $clubLookup);
            $this->assertArrayHasKey($fixture['away_club_id'], $clubLookup);

            $key = $fixture['home_club_id'].'->'.$fixture['away_club_id'];
            $directedPairs[$key] = ($directedPairs[$key] ?? 0) + 1;
        }

        $this->assertCount(12, $directedPairs);

        foreach ($directedPairs as $key => $count) {
            $this->assertSame(1, $count, $key.' should appear exactly once.');
        }
    }
}
