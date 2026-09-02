<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Services\PersonService\Data\GeneratedPlayerData;
use App\Services\PersonService\Data\GeneratedPlayerProfile;
use App\Services\PersonService\Data\PersonInfo;
use App\Services\PersonService\PersonConfig\PersonTypes;
use Carbon\Carbon;

class PlayerAttributesGenerator
{
    private GeneratedPlayerProfile $playerProfile;

    private PersonInfo $personDetails;

    public function __construct(
        private readonly PlayerInitialAttributes $playerInitialAttributes,
        private readonly PersonDetailsGenerator $personDetailsGenerator,
        private readonly PlayerPotentialByAge $playerPotentialByAge = new PlayerPotentialByAge,
    ) {}

    public function setPlayerDetails(GeneratedPlayerProfile $playerProfile): self
    {
        $this->playerProfile = $playerProfile;
        $this->personDetails = $this->personDetailsGenerator->generate(PersonTypes::PLAYER);

        return $this;
    }

    public function generateAttributes(): GeneratedPlayerData
    {
        $attributes = $this->playerInitialAttributes
            ->setPlayerPosition($this->playerProfile->position)
            ->setPlayerPotentialByCategory((array) $this->playerProfile->potentialByCategory)
            ->initAllAttributes();

        return new GeneratedPlayerData(
            personDetails: $this->personDetails,
            position: $this->playerProfile->position,
            potentialByCategory: $this->playerProfile->potentialByCategory,
            maxPotential: $this->playerProfile->potential,
            potential: $this->currentPotential(),
            positions: [$this->playerProfile->position],
            attributes: $attributes,
        );
    }

    private function currentPotential(): float
    {
        $currentAge = Carbon::parse($this->personDetails->dateOfBirth)->age;

        return $this->playerPotentialByAge->calculate($this->playerProfile->potential, $currentAge);
    }
}
