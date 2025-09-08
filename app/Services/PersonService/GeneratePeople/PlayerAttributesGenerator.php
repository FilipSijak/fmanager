<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Services\PersonService\PersonConfig\PersonTypes;
use Carbon\Carbon;
use Faker\Factory;

class PlayerAttributesGenerator
{
    public function generateAttributes(\stdClass $playerPotentialWithPosition)
    {
        $this->player = new \stdClass();
        $this->player->position = $playerPotentialWithPosition->position;
        $this->player->potentialByCategory = $playerPotentialWithPosition->potentialByCategory;
        $this->player->max_potential = $playerPotentialWithPosition->potential;

        $this->setInitialAttributes();
        $this->setPlayerPositionList();
        $this->setPersonInfo();
        $this->setMaxPotential();

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

    protected function setMaxPotential()
    {
        $currentAge = Carbon::parse($this->player->dob)->age;

        $agePotentialBrackets = [
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
        $ageBracketsKeys = array_keys($agePotentialBrackets);

        foreach ($agePotentialBrackets as $age => $potential) {
            if ($currentAge >= $age && next($ageBracketsKeys) && $currentAge < next($ageBracketsKeys)) {
                $this->player->potential = $this->player->max_potential * $potential;
            }
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
