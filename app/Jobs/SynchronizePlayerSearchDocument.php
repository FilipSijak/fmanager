<?php

namespace App\Jobs;

use App\Contracts\Search\PlayerSearchIndexer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SynchronizePlayerSearchDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [5, 30, 120];

    public function __construct(public readonly int $playerId) {}

    public function handle(PlayerSearchIndexer $indexer): void
    {
        $indexer->synchronizePlayer($this->playerId);
    }
}
