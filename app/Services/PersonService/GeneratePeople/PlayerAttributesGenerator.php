<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Services\PersonService\PersonConfig\PersonTypes;
use Faker\Factory;

class PlayerAttributesGenerator
{
    public function generateAttributes(\stdClass $playerPotential)
    {
        $this->player = new \stdClass();
        $this->player->position = $playerPotential->position;
        $this->player->potentialByCategory = $playerPotential->potentialByCategory;
        $this->player->potential = $playerPotential->potential;

        $this->setInitialAttributes();

        $this->setPlayerPositionList();

        $this->setPersonInfo();

        return $this->player;
    }

    protected function setInitialAttributes()
    {
        $initialAttributes = new PlayerInitialAttributes(
            (array)$this->player->potentialByCategory,
            $this->player->position
        );

        $playerInitialAttributes = $initialAttributes->getAllAttributes();

        foreach ($playerInitialAttributes as $attribute => $value) {
            $this->player->{$attribute} = $value;
        }
    }

    protected function setPlayerPositionList()
    {

    }

    protected function setPersonInfo()
    {
        $faker = Factory::create();
        $startDate = '-40 years';
        $endDate   = '-16 years';

        $dob = $faker->dateTimeBetween($startDate, $endDate, $timezone = null);
        $dob = date_format($dob, 'Y-m-d');

        $this->player->first_name   = $faker->firstNameMale;
        $this->player->last_name    = $faker->lastName;
        $this->player->country_code = 'GBR';
        $this->player->dob          = $dob;
    }
}
