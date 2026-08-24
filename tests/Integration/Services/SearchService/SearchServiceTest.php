<?php

namespace Tests\Integration\Services\SearchService;

use App\Models\Club;
use App\Models\Player;
use App\Models\Transfer;
use App\Services\SearchService\SearchService;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SearchServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function regular_search_delegates_normalized_attributes_to_player_search_repository(): void
    {
        app(GameContext::class)->set(1, null, '2024-01-01');
        $matching = Player::factory()->create([
            'instance_id' => 1,
            'pace' => 18,
            'strength' => 10,
        ]);
        Player::factory()->create([
            'instance_id' => 1,
            'pace' => 17,
            'strength' => 20,
        ]);

        $players = app(SearchService::class)->searchForPlayersByAttributes([[
            'pace' => 18,
            'technical' => 0,
            'mental' => null,
            'strength' => 10,
        ]]);

        $this->assertSame([$matching->id], $players->pluck('id')->all());
    }

    #[Test]
    public function transfer_search_delegates_normalized_attributes_and_club_to_transfer_repository(): void
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

        $players = app(SearchService::class)->transferSearchForPlayerByAttributes($buyingClub, [[
            'pace' => 18,
            'technical' => 0,
            'mental' => null,
            'strength' => 10,
        ]]);

        $this->assertSame([$matching->id], $players->pluck('id')->all());
    }
}
