<?php

namespace Tests\Integration\Player;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Person;
use App\Models\Player;
use App\Services\SearchService\SearchService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class PlayerSearchApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_ranked_player_search_results(): void
    {
        $instance = Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
        ]);
        $club = Club::factory()->create([
            'id' => 20,
            'instance_id' => $instance->id,
            'name' => 'Managed FC',
        ]);
        $person = Person::factory()->create([
            'instance_id' => $instance->id,
            'first_name' => 'Alpha',
            'last_name' => 'Striker',
        ]);
        $player = Player::factory()->create([
            'instance_id' => $instance->id,
            'person_id' => $person->id,
            'club_id' => $club->id,
        ])->load(['person', 'club', 'contract']);

        $searchService = Mockery::mock(SearchService::class);
        $searchService->shouldReceive('searchPlayersByName')
            ->once()
            ->with('Alpha', 10)
            ->andReturn(new Collection([$player]));
        $this->app->instance(SearchService::class, $searchService);

        $this->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson('/api/player/search?q=Alpha&limit=10')
            ->assertOk()
            ->assertJsonPath('data.0.id', $player->id)
            ->assertJsonPath('data.0.first_name', 'Alpha')
            ->assertJsonPath('data.0.club.name', 'Managed FC');
    }

    #[Test]
    public function it_validates_the_search_query(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
        ]);

        $this->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson('/api/player/search?q=a&limit=51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q', 'limit']);
    }

    #[Test]
    public function it_returns_service_unavailable_when_elasticsearch_fails(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
        ]);

        $searchService = Mockery::mock(SearchService::class);
        $searchService->shouldReceive('searchPlayersByName')
            ->once()
            ->andThrow(new RuntimeException('Connection failed'));
        $this->app->instance(SearchService::class, $searchService);

        $this->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson('/api/player/search?q=Alpha')
            ->assertStatus(503)
            ->assertJsonPath('error', 'Player search is temporarily unavailable.');
    }
}
