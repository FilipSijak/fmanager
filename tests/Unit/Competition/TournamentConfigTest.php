<?php

namespace Tests\Unit\Competition;

use App\Services\CompetitionService\Competitions\TournamentConfig;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TournamentConfigTest extends TestCase
{
    #[Test]
    public function it_derives_tournament_dates_from_the_season(): void
    {
        $config = new TournamentConfig('2026-08-15');

        $firstLeg = $config->firstTuesdayOnOrAfter($config->getStartDate());

        $this->assertSame('2026-08-15', $config->getStartDate()->toDateString());
        $this->assertSame('2027-02-15', $config->getWinterKnockoutStartDate()->toDateString());
        $this->assertSame('2026-08-18', $firstLeg->toDateString());
        $this->assertSame('2026-08-25', $config->getSecondLegDate($firstLeg)->toDateString());
        $this->assertSame('2026-09-01', $config->getNextRoundStartDate('2026-08-25')->toDateString());
    }
}
