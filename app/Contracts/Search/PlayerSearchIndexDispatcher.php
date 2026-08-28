<?php

namespace App\Contracts\Search;

interface PlayerSearchIndexDispatcher
{
    public function synchronizePlayer(int $playerId): void;

    public function reindexInstance(int $instanceId): void;
}
