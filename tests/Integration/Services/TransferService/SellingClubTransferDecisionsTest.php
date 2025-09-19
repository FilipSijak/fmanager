<?php

namespace Tests\Integration\Services\TransferService;

use App\Models\Account;
use App\Models\Club;
use App\Models\Player;
use App\Models\Transfer;
use App\Repositories\TransferRepository;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;
use App\Services\TransferService\TransferConsiderations\ClubConsideration;
use App\Services\TransferService\TransferConsiderations\PlayerConsideration;
use App\Services\TransferService\TransferConsiderations\TransferConsiderations;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SellingClubTransferDecisionsTest extends TestCase
{
    use DatabaseMigrations;

    #[Test]
    public function it_can_make_a_transfer_decision()
    {
        (new DatabaseSeeder())->run();

        $transferConsiderations = new TransferConsiderations(
            app()->make(PlayerConsideration::class),
            app()->make(ClubConsideration::class),
            app()->make(TransferRepository::class)
        );

        Club::factory()->create();
        Account::factory()->create();
        $transfer = Transfer::factory()->create();

        Player::factory()
              ->count(5)
              ->sequence(function (Sequence $sequence) {
                  return ['position' => PlayerPositionConfig::PLAYER_POSITIONS[$sequence->index + 1]];
              })
              ->create();

        Player::factory()
              ->count(4)
              ->sequence(function () {
                  return ['position' => 'CB'];
              })
              ->create();

        $decision = $transferConsiderations->sellingClubDecision($transfer);

        $this->assertTrue($decision);
    }
}
