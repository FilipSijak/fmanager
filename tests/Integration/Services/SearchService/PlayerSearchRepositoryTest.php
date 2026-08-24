<?php

namespace Tests\Integration\Services\SearchService;

use App\Models\Player;
use App\Repositories\PlayerSearchRepository;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerSearchRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_only_matching_active_players_from_the_current_instance(): void
    {
        app(GameContext::class)->set(1, null, '2024-01-01');
        $matching = Player::factory()->create([
            'instance_id' => 1,
            'pace' => 18,
            'technical' => 15,
        ]);
        Player::factory()->create([
            'instance_id' => 1,
            'pace' => 17,
            'technical' => 20,
        ]);
        Player::factory()->create([
            'instance_id' => 1,
            'pace' => 20,
            'technical' => 20,
            'is_retired' => true,
        ]);
        Player::factory()->create([
            'instance_id' => 2,
            'pace' => 20,
            'technical' => 20,
        ]);

        $players = app(PlayerSearchRepository::class)
            ->findByAttributes(['pace' => 18, 'technical' => 15]);

        $this->assertSame([$matching->id], $players->pluck('id')->all());
    }

    #[Test]
    public function it_rejects_unknown_attribute_columns(): void
    {
        app(GameContext::class)->set(1, null, '2024-01-01');

        $this->expectException(\InvalidArgumentException::class);
        app(PlayerSearchRepository::class)
            ->findByAttributes(['not_a_column' => 1]);
    }
}
