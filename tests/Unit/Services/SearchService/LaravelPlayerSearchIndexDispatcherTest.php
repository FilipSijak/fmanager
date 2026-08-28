<?php

namespace Tests\Unit\Services\SearchService;

use App\Contracts\Search\PlayerSearchIndexDispatcher;
use App\Jobs\ReindexInstancePlayers;
use App\Jobs\SynchronizePlayerSearchDocument;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LaravelPlayerSearchIndexDispatcherTest extends TestCase
{
    #[Test]
    public function it_dispatches_single_player_synchronization_after_commit(): void
    {
        Queue::fake();

        app(PlayerSearchIndexDispatcher::class)->synchronizePlayer(42);

        Queue::assertPushedOn('elasticsearch', SynchronizePlayerSearchDocument::class, function ($job): bool {
            return $job->playerId === 42 && $job->afterCommit === true;
        });
    }

    #[Test]
    public function it_dispatches_an_instance_reindex_after_commit(): void
    {
        Queue::fake();

        app(PlayerSearchIndexDispatcher::class)->reindexInstance(7);

        Queue::assertPushedOn('elasticsearch', ReindexInstancePlayers::class, function ($job): bool {
            return $job->instanceId === 7 && $job->afterCommit === true;
        });
    }
}
