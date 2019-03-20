<?php

namespace App\GameEngine\Player;

use App\GameEngine\Player\PlayerPosition;
use App\GameEngine\Player\PlayerPotential;
use App\GameEngine\Player\PlayerInitialAttributes;

use App\Repositories\PlayerRepository;

use App\Player;

class PlayerCreation
{
    protected $player;

    public function setupPlayer($coeff)
    {
        $this->player = new Player();

        // player potential
        $playerPotential = (array)$this->setPlayerPotential($coeff);
        //dd($playerPotential);
        // player position
        $playerPosition = $this->setPlayerPosition();
        //dd($playerPosition);
        // player attributes
        $initialAttributeValues = $this->setPlayerInitialAttributes($playerPotential, $playerPosition);
        //dd($initialAttributeValues);
        // player other positions based on attributes
        $playerPositionList = $this->setPlayerPositionList($initialAttributeValues);
        //dd($playerPositionList);
        // set player info
        $playerInfo = $this->setPlayerInfo();
        //dd($playerInfo);
        //save player to the database
        
        $player_data = array_merge($playerPotential, $initialAttributeValues, $playerPositionList, $playerInfo);
        dd($player_data);
        return $this->player;
    }

    private function setPlayerPotential($coeff)
    {
        return PlayerPotential::calculatePlayerPotential($coeff);
    }

    private function setPlayerPosition()
    {
        return PlayerPosition::setRandomPosition();
    }

    private function setPlayerInitialAttributes($playerPotential, $playerPosition): array
    {
        $playerAttributesInstance = new PlayerInitialAttributes($playerPotential, $playerPosition);
        return $playerAttributesInstance->getAllAttributeValues();

    }

    private function setPlayerPositionList($initialAttributeValues): array
    {
        return PlayerPosition::setInitialPositionsBasedOnAttributes($initialAttributeValues);
    }

    private function setPlayerInfo()
    {
        $pr = new PlayerRepository();
        return $pr->setPlayerInitialInfo();
    }
}