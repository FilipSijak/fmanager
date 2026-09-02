<?php

namespace App\Repositories\Interfaces;

use Illuminate\Support\LazyCollection;

interface IPlayerRepository
{
    public function activePlayers(int $instanceId): LazyCollection;

    public function playersEligibleForRetirement(int $instanceId, string $date): LazyCollection;

    public function retirePlayer(int $playerId): bool;
}
