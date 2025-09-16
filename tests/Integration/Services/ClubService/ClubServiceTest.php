<?php

namespace Tests\Integration\Services\ClubService;

use App\Models\Account;
use App\Models\Club;
use App\Models\Player;
use App\Models\Transfer;
use App\Repositories\ClubRepository;
use App\Repositories\TransferSearchRepository;
use App\Services\ClubService\ClubService;
use App\Services\ClubService\FinancialAnalysis\ClubFinancialAnalysis;
use App\Services\ClubService\SquadAnalysis\SquadAnalysis;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;
use App\Services\SearchService\SearchService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClubServiceTest extends TestCase
{
    use DatabaseMigrations;

    #[Test]
    public function it_can_make_a_transfer_decision()
    {
        (new DatabaseSeeder())->run();

        $clubService = new ClubService(
            app()->make(SquadAnalysis::class),
            app()->make(ClubFinancialAnalysis::class),
            app()->make(SearchService::class),
            app()->make(ClubRepository::class)
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

        $decision = $clubService->clubSellingDecision($transfer);

        $this->assertTrue($decision);
    }
}
