<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Models\Player;
use App\Services\PersonService\Data\GeneratedPlayerData;

class PersonFactory
{
    public function createPlayer(GeneratedPlayerData $generatedPlayer, int $instanceId): Player
    {
        return (new PlayerType)->create($generatedPlayer, $instanceId);
    }
}
