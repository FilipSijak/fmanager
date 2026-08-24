<?php

namespace Tests\Integration\Services\SearchService;

use App\Models\Player;
use App\Services\SearchService\SearchService;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SearchServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_searches_for_players_using_normalized_attributes(): void
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

        $players = app(SearchService::class)->searchForPlayersByAttributes([
            'pace' => 18,
            'strength' => 10,
        ]);

        $this->assertSame([$matching->id], $players->pluck('id')->all());
    }
}
