<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Repositories\ElasticsearchPlayerSearchRepository;
use App\Support\GameContext;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ElasticsearchPlayerSearchRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_fetches_the_requested_offset_and_preserves_search_rank_when_loading_players(): void
    {
        app(GameContext::class)->set(1, null);
        $first = Player::factory()->create(['instance_id' => 1]);
        $second = Player::factory()->create(['instance_id' => 1]);
        $history = [];
        $client = $this->client([$second->id, $first->id], 5, $history);

        $page = (new ElasticsearchPlayerSearchRepository($client, app(GameContext::class)))
            ->searchByName('Alpha', 2, 2);

        $this->assertSame([$second->id, $first->id], $page->getCollection()->pluck('id')->all());
        $this->assertSame(5, $page->total());
        $this->assertSame(2, $page->currentPage());
        $this->assertSame(2, $page->perPage());
        $body = json_decode((string) $history[0]['request']->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(2, $body['from']);
        $this->assertSame(2, $body['size']);
        $this->assertTrue($body['track_total_hits']);
        $this->assertSame([['_score' => 'desc'], ['id' => 'asc']], $body['sort']);
        $this->assertSame([['term' => ['instance_id' => 1]], ['term' => ['is_retired' => false]]], $body['query']['bool']['filter']);
    }

    #[Test]
    public function an_empty_search_page_preserves_the_total_and_current_page(): void
    {
        app(GameContext::class)->set(1, null);
        $history = [];
        $client = $this->client([], 21, $history);

        $page = (new ElasticsearchPlayerSearchRepository($client, app(GameContext::class)))
            ->searchByName('Alpha', 20, 3);

        $this->assertSame([], $page->items());
        $this->assertSame(21, $page->total());
        $this->assertSame(3, $page->currentPage());
    }

    #[Test]
    public function stale_index_hits_cannot_expose_foreign_or_retired_players(): void
    {
        app(GameContext::class)->set(1, null);
        $active = Player::factory()->create(['instance_id' => 1]);
        $foreign = Player::factory()->create(['instance_id' => 2]);
        $retired = Player::factory()->create(['instance_id' => 1, 'is_retired' => true]);
        $history = [];
        $client = $this->client([$foreign->id, $active->id, $retired->id], 3, $history);

        $page = (new ElasticsearchPlayerSearchRepository($client, app(GameContext::class)))
            ->searchByName('Alpha', 20, 1);

        $this->assertSame([$active->id], $page->getCollection()->pluck('id')->all());
    }

    /**
     * @param  list<int>  $ids
     * @param  array<int, array<string, mixed>>  $history
     */
    private function client(array $ids, int $total, array &$history): Client
    {
        $response = new Response(200, ['Content-Type' => 'application/json', 'X-Elastic-Product' => 'Elasticsearch'], json_encode([
            'hits' => ['total' => ['value' => $total, 'relation' => 'eq'],
                'hits' => array_map(fn (int $id): array => ['_source' => ['id' => $id]], $ids)],
        ], JSON_THROW_ON_ERROR));
        $handler = HandlerStack::create(new MockHandler([$response]));
        $handler->push(Middleware::history($history));

        return ClientBuilder::create()->setHttpClient(new HttpClient(['handler' => $handler]))->build();
    }
}
