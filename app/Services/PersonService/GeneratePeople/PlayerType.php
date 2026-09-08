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
        $player->current_technical_potential = $generatedPlayer->currentPotentialByCategory->technical;
        $player->current_mental_potential = $generatedPlayer->currentPotentialByCategory->mental;
        $player->current_physical_potential = $generatedPlayer->currentPotentialByCategory->physical;

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
