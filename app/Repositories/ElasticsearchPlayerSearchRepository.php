<?php

namespace App\Repositories;

use App\Models\Player;
use App\Support\GameContext;
use Elastic\Elasticsearch\Client;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

final class ElasticsearchPlayerSearchRepository
{
    public function __construct(
        private readonly Client $client,
        private readonly GameContext $gameContext,
    ) {}

    /** @return LengthAwarePaginator<int, Player> */
    public function searchByName(string $query, int $limit = 20, int $page = 1): LengthAwarePaginator
    {
        $response = $this->client->search([
            'index' => config('elasticsearch.player_index'),
            'body' => [
                'size' => $limit,
                'from' => ($page - 1) * $limit,
                'track_total_hits' => true,
                'sort' => [['_score' => 'desc'], ['id' => 'asc']],
                '_source' => ['id'],
                'query' => [
                    'bool' => [
                        'filter' => [
                            ['term' => ['instance_id' => $this->gameContext->instanceId()]],
                            ['term' => ['is_retired' => false]],
                        ],
                        'must' => [[
                            'multi_match' => [
                                'query' => $query,
                                'fields' => ['full_name^2', 'first_name', 'last_name'],
                            ],
                        ]],
                    ],
                ],
            ],
        ])->asArray();

        $ids = collect($response['hits']['hits'] ?? [])
            ->map(fn (array $hit): int => (int) $hit['_source']['id'])
            ->all();

        $rank = array_flip($ids);

        $players = $ids === [] ? new Collection : Player::query()
            ->with(['person', 'club', 'contract'])
            ->forInstance($this->gameContext->instanceId())
            ->active()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Player $player): int => $rank[$player->id])
            ->values();

        return new LengthAwarePaginator($players, (int) ($response['hits']['total']['value'] ?? 0), $limit, $page);
    }
}
