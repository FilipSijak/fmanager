<?php

namespace App\Contracts\Search;

interface PlayerSearchIndexer
{
    public function synchronizePlayer(int $playerId): void;

    public function reindexInstance(int $instanceId): void;
}
