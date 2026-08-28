<?php

namespace Tests\Unit\Jobs;

use App\Contracts\Search\PlayerSearchIndexer;
use App\Jobs\ReindexInstancePlayers;
use App\Jobs\SynchronizePlayerSearchDocument;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PlayerSearchIndexJobsTest extends TestCase
{
    #[Test]
    public function the_single_player_job_delegates_to_the_indexer(): void
    {
        $indexer = Mockery::mock(PlayerSearchIndexer::class);
        $indexer->shouldReceive('synchronizePlayer')->once()->with(42);

        (new SynchronizePlayerSearchDocument(42))->handle($indexer);
    }

    #[Test]
    public function the_instance_job_delegates_to_the_indexer(): void
    {
        $indexer = Mockery::mock(PlayerSearchIndexer::class);
        $indexer->shouldReceive('reindexInstance')->once()->with(7);

        (new ReindexInstancePlayers(7))->handle($indexer);
    }
}
