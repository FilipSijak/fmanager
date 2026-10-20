<?php

namespace Tests\Integration\Player;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Person;
use App\Models\Player;
use App\Services\SearchService\SearchService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
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
            ->with('Alpha', 10, 1)
            ->andReturn(new LengthAwarePaginator(new Collection([$player]), 25, 10, 1));
        $this->app->instance(SearchService::class, $searchService);

        $this->actingAs(Instance::where('instance_hash', 'current-instance')->firstOrFail()->user)
            ->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson('/api/player/search?q=Alpha&limit=10')
            ->assertOk()
            ->assertJsonPath('data.0.id', $player->id)
            ->assertJsonPath('data.0.first_name', 'Alpha')
            ->assertJsonPath('data.0.club.name', 'Managed FC')
            ->assertJsonPath('meta', ['current_page' => 1, 'per_page' => 10, 'total' => 25, 'last_page' => 3, 'has_more' => true, 'limited' => false]);
    }

    #[Test]
    public function it_validates_the_search_query(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
        ]);

        $this->actingAs(Instance::where('instance_hash', 'current-instance')->firstOrFail()->user)
            ->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson('/api/player/search?q=a&limit=51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q', 'limit']);
    }

    #[Test]
    public function it_returns_an_empty_list_when_no_players_match(): void
    {
        $instance = Instance::factory()->create();
        $searchService = Mockery::mock(SearchService::class);
        $searchService->shouldReceive('searchPlayersByName')->once()->with('Unknown', 20, 1)
            ->andReturn(new LengthAwarePaginator(new Collection, 0, 20, 1));
        $this->app->instance(SearchService::class, $searchService);

        $this->actingAs($instance->user)->withHeaders(['instanceHash' => $instance->instance_hash])
            ->getJson('/api/player/search?q=Unknown')
            ->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.last_page', 1)->assertJsonPath('meta.has_more', false);
    }

    #[Test]
    public function it_returns_the_requested_page_with_its_pagination_metadata(): void
    {
        $instance = Instance::factory()->create();
        $player = Player::factory()->create(['instance_id' => $instance->id])->load(['person', 'club', 'contract']);
        $this->mock(SearchService::class)->shouldReceive('searchPlayersByName')->once()->with('Alpha', 20, 2)
            ->andReturn(new LengthAwarePaginator(new Collection([$player]), 41, 20, 2));

        $this->actingAs($instance->user)->withHeaders(['instanceHash' => $instance->instance_hash])
            ->getJson('/api/player/search?q=Alpha&page=2')->assertOk()
            ->assertJsonPath('data.0.id', $player->id)
            ->assertJsonPath('meta', ['current_page' => 2, 'per_page' => 20, 'total' => 41, 'last_page' => 3, 'has_more' => true, 'limited' => false]);
    }

    #[Test]
    public function pages_past_the_last_match_return_an_empty_list_without_a_next_page(): void
    {
        $instance = Instance::factory()->create();
        $this->mock(SearchService::class)->shouldReceive('searchPlayersByName')->once()->with('Alpha', 20, 3)
            ->andReturn(new LengthAwarePaginator(new Collection, 21, 20, 3));

        $this->actingAs($instance->user)->withHeaders(['instanceHash' => $instance->instance_hash])
            ->getJson('/api/player/search?q=Alpha&page=3')->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('meta.current_page', 3)->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.has_more', false);
    }

    #[Test]
    #[TestWith(['0'])]
    #[TestWith(['-1'])]
    #[TestWith(['abc'])]
    #[TestWith(['1.5'])]
    #[TestWith(['501'])]
    #[TestWith(['201', 50])]
    public function invalid_or_excessive_pages_are_rejected(string $page, int $limit = 20): void
    {
        $instance = Instance::factory()->create();
        $this->mock(SearchService::class)->shouldNotReceive('searchPlayersByName');

        $this->actingAs($instance->user)->withHeaders(['instanceHash' => $instance->instance_hash])
            ->getJson('/api/player/search?q=Alpha&page='.$page.'&limit='.$limit)
            ->assertUnprocessable()->assertJsonValidationErrors('page');
    }

    #[Test]
    public function pagination_stops_at_the_search_window_and_marks_the_total_as_limited(): void
    {
        $instance = Instance::factory()->create();
        $this->mock(SearchService::class)->shouldReceive('searchPlayersByName')->once()->with('Alpha', 20, 500)
            ->andReturn(new LengthAwarePaginator(new Collection, 10001, 20, 500));

        $this->actingAs($instance->user)->withHeaders(['instanceHash' => $instance->instance_hash])
            ->getJson('/api/player/search?q=Alpha&page=500')->assertOk()
            ->assertJsonPath('meta.total', 10001)->assertJsonPath('meta.last_page', 500)
            ->assertJsonPath('meta.has_more', false)->assertJsonPath('meta.limited', true);
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

        $this->actingAs(Instance::where('instance_hash', 'current-instance')->firstOrFail()->user)
            ->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson('/api/player/search?q=Alpha')
            ->assertStatus(503)
            ->assertJsonPath('error', 'Player search is temporarily unavailable.');
    }
}
