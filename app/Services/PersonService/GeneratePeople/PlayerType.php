<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Models\Player;
use App\Services\PersonService\Data\GeneratedPlayerData;

class PlayerType
{
    public function create(GeneratedPlayerData $generatedPlayer, int $instanceId): Player
    {
        $player = new Player;
        $player->position = $generatedPlayer->position;
        $player->max_potential = $generatedPlayer->maxPotential;
        $player->potential = $generatedPlayer->potential;

        foreach ($generatedPlayer->attributes as $attribute => $value) {
            $player->{$attribute} = $value;
        }

        $player->setPersonIdentity($generatedPlayer->personDetails);
        $player->game_id = $instanceId;
        $player->setPositions($generatedPlayer->positions);
        $player->setAttributesCategoriesPotential([
            'potentialByCategory' => $generatedPlayer->potentialByCategory,
        ]);

        return $player;
    }
}
