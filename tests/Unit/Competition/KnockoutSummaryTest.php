<?php

namespace Tests\Unit\Competition;

use App\Repositories\GameRepository;
use App\Services\CompetitionService\Competitions\KnockoutSummary;
use App\Services\CompetitionService\Competitions\KnockoutSummaryRoundsData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnockoutSummaryTest extends TestCase
{
    #[Test]
    public function itCanGetCurrentRoundData()
    {
        $summary = file_get_contents(__DIR__ . '/../../fixtures/knockoutSummary.json');
        $repository = $this->getMockBuilder(GameRepository::class)->getMock();
        $knockoutSummary = new KnockoutSummaryRoundsData($repository);

        $currentRoundData = $knockoutSummary->getCurrentRound($summary);
        $expectedNumberOfPairsFirstGroup = 4;

        $this->assertCount($expectedNumberOfPairsFirstGroup, $currentRoundData['first_group']);
    }

    #[Test]
    public function itCanGetCurrentRoundDataFromTwoRounds()
    {
        $summary = file_get_contents(__DIR__ . '/../../fixtures/knockoutSummaryTwoRounds.json');
        $repository = $this->getMockBuilder(GameRepository::class)->getMock();
        $knockoutSummary = new KnockoutSummaryRoundsData($repository);

        $currentRoundData = $knockoutSummary->getCurrentRound($summary);
        $secondRoundFirsPairWinner = $currentRoundData['first_group'][0]['winner'];
        $expectedSecondRoundFirsPairWinner = 1;

        $this->assertCount(4, $currentRoundData['first_group']);
        $this->assertEquals($expectedSecondRoundFirsPairWinner,$secondRoundFirsPairWinner);
    }

    #[Test]
    public function itCanGeFinalsGame()
    {
        $summary = file_get_contents(__DIR__ . '/../../fixtures/knockoutSummaryFinals.json');
        $repository = $this->getMockBuilder(GameRepository::class)->getMock();
        $knockoutSummary = new KnockoutSummaryRoundsData($repository);

        $currentRoundData = $knockoutSummary->getCurrentRound($summary);
        $finalsMatchId = $currentRoundData;
        $finalsExpectedId = 1;

        $this->assertEquals($finalsExpectedId, $finalsMatchId);
    }
}
