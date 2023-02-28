<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Services\PersonService\GeneratePeople\PlayerType;

class PersonFactory
{
    public function createPlayer(\stdClass $generatedAttributes, $instanceId)
    {
        $player = new PlayerType();

        return $player->create($generatedAttributes, $instanceId);
    }
}
