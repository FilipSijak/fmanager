<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Services\PersonService\Data\GeneratedPlayerData;
use App\Services\PersonService\Data\GeneratedPlayerProfile;
use App\Services\PersonService\Data\PersonInfo;
use App\Services\PersonService\PersonConfig\PersonTypes;
use Carbon\Carbon;

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

    private GeneratedPlayerProfile $playerProfile;

    private PersonInfo $personDetails;

    public function __construct(
        private readonly PlayerInitialAttributes $playerInitialAttributes,
        private readonly PersonDetailsGenerator $personDetailsGenerator,
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
        $ages = array_keys(self::AGE_POTENTIAL_BRACKETS);

        for ($index = 0; $index < count($ages) - 1; $index++) {
            if ($currentAge >= $ages[$index] && $currentAge < $ages[$index + 1]) {
                return $this->playerProfile->potential * self::AGE_POTENTIAL_BRACKETS[$ages[$index]];
            }
        }

        $oldestAge = end($ages);

        return $this->playerProfile->potential * self::AGE_POTENTIAL_BRACKETS[$oldestAge];
    }
}
