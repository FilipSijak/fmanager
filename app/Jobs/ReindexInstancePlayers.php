<?php

namespace App\Jobs;

use App\Contracts\Search\PlayerSearchIndexer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ReindexInstancePlayers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [10, 60, 180];

    public function __construct(public readonly int $instanceId) {}

    public function handle(PlayerSearchIndexer $indexer): void
    {
        $indexer->reindexInstance($this->instanceId);
    }
}
