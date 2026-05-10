<?php

namespace Tests\Integration\Services\ClubService;

use App\DataModels\PlayerImportance;
use App\Models\Account;
use App\Models\Club;
use App\Models\Player;
use App\Models\Transfer;
use App\Models\TransferFinancialDetails;
use App\Services\TransferService\TransferEntityAnalysis\ClubFinancialTransferAnalysis;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinancialAnalysisTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_check_transfer_offer_is_acceptable()
    {
        (new DatabaseSeeder())->run();

        $sellingClub = Club::factory()->create();
        Account::factory()->create(['club_id' => $sellingClub->id]);
        $player = Player::factory()->create([
            'club_id' => $sellingClub->id,
            'value' => 10000,
        ]);
        $transfer = Transfer::factory()->create([
            'target_club_id' => $sellingClub->id,
            'player_id' => $player->id,
        ]);
        TransferFinancialDetails::factory()->create([
            'transfer_id' => $transfer->id,
            'amount' => 10000,
        ]);

        $playerImportance = new PlayerImportance();
        $playerImportance->setAcceptableTransfer(true);

        $decision = (new ClubFinancialTransferAnalysis())->isFinanciallyAcceptableTransfer($transfer, $playerImportance);

        $this->assertTrue($decision->isAcceptableTransfer());
    }
}
