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
        $playerPotential = $this->setPlayerPotential($coeff);

        // player position
        $playerPosition = $this->setPlayerPosition();

        // player attributes
        $initialAttributeValues = $this->setPlayerInitialAttributes($playerPotential, $playerPosition);

        // player other positions based on attributes
        $playerPositionList = $this->setPlayerPositionList($initialAttributeValues);

        // set player info
        $playerInfo = $this->setPlayerInfo();

        //save player to the database

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