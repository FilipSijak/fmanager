<?php

namespace Tests\Integration\Services\ClubService;

use App\Models\Account;
use App\Models\Club;
use App\Models\Player;
use App\Models\Transfer;
use App\Models\TransferFinancialDetails;
use App\Services\TransferService\TransferConsiderations\ClubConsideration;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClubServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_make_a_transfer_decision()
    {
        (new DatabaseSeeder())->run();

        $buyingClub = Club::factory()->create(['id' => 1]);
        $sellingClub = Club::factory()->create(['id' => 2]);
        Account::factory()->create(['club_id' => $sellingClub->id]);

        Player::factory()
              ->count(5)
              ->sequence(function (Sequence $sequence) use ($sellingClub) {
                  return [
                      'club_id' => $sellingClub->id,
                      'position' => PlayerPositionConfig::PLAYER_POSITIONS[$sequence->index + 1],
                      'potential' => 100,
                  ];
              })
              ->create();

        Player::factory()
              ->count(4)
              ->sequence(function () use ($sellingClub) {
                  return [
                      'club_id' => $sellingClub->id,
                      'position' => 'CB',
                      'potential' => 100,
                  ];
              })
              ->create();

        Player::factory()->create([
            'club_id' => $sellingClub->id,
            'position' => 'ST',
            'value' => 10000,
            'potential' => 100,
        ]);

        $player = Player::factory()->create([
            'club_id' => $sellingClub->id,
            'position' => 'ST',
            'value' => 10000,
            'potential' => 80,
        ]);

        $transfer = Transfer::factory()->create([
            'source_club_id' => $buyingClub->id,
            'target_club_id' => $sellingClub->id,
            'player_id' => $player->id,
        ]);

        TransferFinancialDetails::factory()->create([
            'transfer_id' => $transfer->id,
            'amount' => 10000,
        ]);

        $decision = app()->make(ClubConsideration::class)->considerOffer($transfer);

        $this->assertTrue($decision->getAcceptableTransfer());
    }
}
