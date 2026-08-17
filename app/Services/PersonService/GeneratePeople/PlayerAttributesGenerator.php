<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Services\PersonService\Data\PlayerPotentialData;
use App\Services\PersonService\PersonConfig\PersonTypes;
use Carbon\Carbon;
use stdClass;

class PlayerAttributesGenerator
{
    private const AGE_POTENTIAL_BRACKETS = [
        16 => 0.85,
        18 => 0.9,
        21 => 0.95,
        24 => 1,
        29 => 0.98,
        30 => 0.95,
        32 => 0.92,
        33 => 0.89,
        35 => 0.83,
        38 => 0.75,
        41 => 0.67,
    ];

    private $player;

    private PersonDetailsGenerator $personDetailsGenerator;

    public function __construct(
        private readonly PlayerInitialAttributes $playerInitialAttributes,
        PersonDetailsGenerator $personDetailsGenerator,
    ) {
        $this->personDetailsGenerator = $personDetailsGenerator;
    }

    public function setPlayerDetails(PlayerPotentialData $playerPotentialWithPosition)
    {
        $this->player = new stdClass;
        $this->player->position = $playerPotentialWithPosition->position;
        $this->player->potentialByCategory = $playerPotentialWithPosition->potentialByCategory;
        $this->player->max_potential = $playerPotentialWithPosition->potential;

        $personDetails = $this->personDetailsGenerator->generate(PersonTypes::PLAYER);

        $this->player->personDetails = $personDetails;

        return $this;
    }

    public function generateAttributes()
    {
        $this->setInitialAttributes();
        $this->setPlayerPositionList();
        $this->setCurrentPotential();

        return $this->player;
    }

    protected function setInitialAttributes()
    {
        $playerInitialAttributes = $this->playerInitialAttributes->setPlayerPosition($this->player->position)
            ->setPlayerPotentialByCategory((array) $this->player->potentialByCategory)
            ->initAllAttributes();

        foreach ($playerInitialAttributes as $attribute => $value) {
            $this->player->{$attribute} = $value;
        }
    }

    protected function setCurrentPotential()
    {
        $currentAge = Carbon::parse($this->player->personDetails->dateOfBirth)->age;

        $agePotentialBrackets = self::AGE_POTENTIAL_BRACKETS;

        $ages = array_keys($agePotentialBrackets);
        sort($ages);

        for ($i = 0; $i < count($ages) - 1; $i++) {
            if ($currentAge >= $ages[$i] && $currentAge < $ages[$i + 1]) {
                $this->player->potential = $this->player->max_potential * $agePotentialBrackets[$ages[$i]];
                break;
            }
        }

        if ($currentAge >= end($ages)) {
            $this->player->potential = $this->player->max_potential * $agePotentialBrackets[end($ages)];
        }
    }

    protected function setPlayerPositionList()
    {
        $this->player->positions = [$this->player->position];
    }
}
