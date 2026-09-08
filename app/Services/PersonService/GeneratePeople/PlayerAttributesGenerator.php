<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Services\PersonService\Data\GeneratedPlayerData;
use App\Services\PersonService\Data\GeneratedPlayerProfile;
use App\Services\PersonService\Data\PersonInfo;
use App\Services\PersonService\PersonConfig\PersonTypes;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class PlayerAttributesGenerator
{
    private GeneratedPlayerProfile $playerProfile;

    private PersonInfo $personDetails;

    public function __construct(
        private readonly PlayerInitialAttributes $playerInitialAttributes,
        private readonly PersonDetailsGenerator $personDetailsGenerator,
        private readonly PlayerPotential $playerPotential = new PlayerPotential,
    ) {}

    public function setPlayerDetails(GeneratedPlayerProfile $playerProfile): self
    {
        $this->playerProfile = $playerProfile;
        $this->personDetails = $this->personDetailsGenerator->generate(PersonTypes::PLAYER);

        return $this;
    }

    public function generateAttributes(?CarbonInterface $asOfDate = null): GeneratedPlayerData
    {
        $asOfDate ??= Carbon::now();
        $currentPotentialByCategory = $this->playerPotential->potentialByCategoryOnDate(
            $this->playerProfile->potentialByCategory,
            Carbon::parse($this->personDetails->dateOfBirth),
            $asOfDate
        );
        $attributes = $this->playerInitialAttributes
            ->setPlayerPosition($this->playerProfile->position)
            ->setPlayerPotentialByCategory((array) $currentPotentialByCategory)
            ->initAllAttributes();

        return new GeneratedPlayerData(
            personDetails: $this->personDetails,
            position: $this->playerProfile->position,
            potentialByCategory: $this->playerProfile->potentialByCategory,
            maxPotential: $this->playerProfile->potential,
            potential: $this->currentPotential($asOfDate),
            currentPotentialByCategory: $currentPotentialByCategory,
            positions: [$this->playerProfile->position],
            attributes: $attributes,
        );
    }

    private function currentPotential(CarbonInterface $asOfDate): float
    {
        return $this->playerPotential->onDate(
            $this->playerProfile->potential,
            Carbon::parse($this->personDetails->dateOfBirth),
            $asOfDate
        );
    }
}
