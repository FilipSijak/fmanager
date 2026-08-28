<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Search\PlayerDocument;
use Elastic\Elasticsearch\Client;
use Illuminate\Console\Command;

final class IndexPlayersInElasticsearch extends Command
{
    protected $signature = 'elasticsearch:index-players {--recreate : Delete and recreate the player index first}';

    protected $description = 'Create and populate the Elasticsearch player index';

    public function handle(Client $client): int
    {
        $index = config('elasticsearch.player_index');
        $exists = $client->indices()->exists(['index' => $index])->asBool();

        if ($exists && $this->option('recreate')) {
            $client->indices()->delete(['index' => $index]);
            $exists = false;
        }

        if (! $exists) {
            $client->indices()->create([
                'index' => $index,
                'body' => ['mappings' => PlayerDocument::mapping()],
            ]);
        }

        $indexed = 0;

        Player::query()
            ->with('person')
            ->orderBy('id')
            ->chunkById(500, function ($players) use ($client, $index, &$indexed): void {
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

                if ($body === []) {
                    return;
                }

                $response = $client->bulk(['body' => $body])->asArray();

                if ($response['errors'] ?? false) {
                    throw new \RuntimeException('Elasticsearch reported one or more failed player indexing operations.');
                }

                $indexed += $players->count();
                $this->output->write("\rIndexed {$indexed} players");
            });

        $client->indices()->refresh(['index' => $index]);
        $this->newLine();
        $this->info("Player index ready with {$indexed} players.");

        return self::SUCCESS;
    }
}
