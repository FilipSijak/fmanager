<?php

namespace Tests\Unit\Services\TransferService;

use App\Models\Club;
use App\Models\Player;
use App\Services\ClubService\SquadAnalysis\SquadPlayersConfig;
use App\Services\TransferService\TransferEntityAnalysis\SquadTransferAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SquadTransferAnalysisTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_reports_a_position_shortage_when_the_club_has_no_players_in_that_position(): void
    {
        $club = Club::factory()->create(['id' => 1, 'instance_id' => 1]);

        $this->assertTrue((new SquadTransferAnalysis())->positionShortage($club, 'CB'));
    }

    #[Test]
    public function it_reports_no_position_shortage_when_the_position_count_is_met(): void
    {
        $club = Club::factory()->create(['id' => 1, 'instance_id' => 1]);

        Player::factory()
            ->count(SquadPlayersConfig::POSITION_COUNT['CB'])
            ->create([
                'club_id' => $club->id,
                'instance_id' => 1,
                'position' => 'CB',
            ]);

        $this->assertFalse((new SquadTransferAnalysis())->positionShortage($club, 'CB'));
    }
}
