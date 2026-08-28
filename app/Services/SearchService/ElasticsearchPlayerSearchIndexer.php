<?php

namespace App\Services\SearchService;

use App\Contracts\Search\PlayerSearchIndexer;
use App\Models\Player;
use App\Search\PlayerDocument;
use Elastic\Elasticsearch\Client;
use Elastic\Transport\Exception\NotFoundException;
use RuntimeException;

final class ElasticsearchPlayerSearchIndexer implements PlayerSearchIndexer
{
    public function __construct(private readonly Client $client) {}

    public function synchronizePlayer(int $playerId): void
    {
        $this->ensureIndexExists();

        $player = Player::query()
            ->with('person')
            ->find($playerId);

        if ($player === null) {
            $this->deletePlayerDocument($playerId);

            return;
        }

        $this->client->index([
            'index' => config('elasticsearch.player_index'),
            'id' => (string) $player->id,
            'body' => PlayerDocument::fromPlayer($player),
        ]);
    }

    public function reindexInstance(int $instanceId): void
    {
        $this->ensureIndexExists();

        $index = config('elasticsearch.player_index');

        $this->client->deleteByQuery([
            'index' => $index,
            'conflicts' => 'proceed',
            'refresh' => true,
            'body' => [
                'query' => [
                    'term' => ['instance_id' => $instanceId],
                ],
            ],
        ]);

        Player::query()
            ->with('person')
            ->where('instance_id', $instanceId)
            ->orderBy('id')
            ->chunkById(500, function ($players) use ($index): void {
                $body = [];

                foreach ($players as $player) {
                    $body[] = [
                        'index' => [
                            '_index' => $index,
                            '_id' => (string) $player->id,
                        ],
                    ];
                    $body[] = PlayerDocument::fromPlayer($player);
                }

                $response = $this->client->bulk(['body' => $body])->asArray();

                if ($response['errors'] ?? false) {
                    throw new RuntimeException('Elasticsearch reported failed player indexing operations.');
                }
            });

        $this->client->indices()->refresh(['index' => $index]);
    }

    private function ensureIndexExists(): void
    {
        $index = config('elasticsearch.player_index');

        if ($this->client->indices()->exists(['index' => $index])->asBool()) {
            return;
        }

        $this->client->indices()->create([
            'index' => $index,
            'body' => ['mappings' => PlayerDocument::mapping()],
        ]);
    }

    private function deletePlayerDocument(int $playerId): void
    {
        try {
            $this->client->delete([
                'index' => config('elasticsearch.player_index'),
                'id' => (string) $playerId,
            ]);
        } catch (NotFoundException) {
            // The desired state is already reached.
        }
    }
}
