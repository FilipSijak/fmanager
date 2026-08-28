<?php

namespace App\Services\SearchService;

use App\Contracts\Search\PlayerSearchIndexDispatcher;
use App\Jobs\ReindexInstancePlayers;
use App\Jobs\SynchronizePlayerSearchDocument;

final class LaravelPlayerSearchIndexDispatcher implements PlayerSearchIndexDispatcher
{
    public function synchronizePlayer(int $playerId): void
    {
        SynchronizePlayerSearchDocument::dispatch($playerId)
            ->onQueue(config('queue.player_index_queue'))
            ->afterCommit();
    }

    public function reindexInstance(int $instanceId): void
    {
        ReindexInstancePlayers::dispatch($instanceId)
            ->onQueue(config('queue.player_index_queue'))
            ->afterCommit();
    }
}
