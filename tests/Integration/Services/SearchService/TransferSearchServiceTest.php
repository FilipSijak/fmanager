<?php

namespace Tests\Integration\Services\SearchService;

use App\Models\Club;
use App\Models\Player;
use App\Models\Transfer;
use App\Services\SearchService\TransferSearchService;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransferSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_applies_transfer_rules_to_a_player_attribute_search(): void
    {
        app(GameContext::class)->set(1, null, '2024-07-01');
        $buyingClub = Club::factory()->create(['instance_id' => 1]);
        $sellingClub = Club::factory()->create(['instance_id' => 1]);
        $matching = Player::factory()->create([
            'instance_id' => 1,
            'club_id' => $sellingClub->id,
            'pace' => 18,
            'strength' => 10,
        ]);
        $recentlyApproached = Player::factory()->create([
            'instance_id' => 1,
            'club_id' => $sellingClub->id,
            'pace' => 20,
            'strength' => 20,
        ]);
        Transfer::factory()->create([
            'instance_id' => 1,
            'source_club_id' => $buyingClub->id,
            'player_id' => $recentlyApproached->id,
            'offer_date' => '2024-06-01',
        ]);

        $players = app(TransferSearchService::class)->searchForPlayersByAttributes(
            $buyingClub,
            ['pace' => 18, 'strength' => 10],
        );

        $this->assertSame([$matching->id], $players->pluck('id')->all());
    }
}
