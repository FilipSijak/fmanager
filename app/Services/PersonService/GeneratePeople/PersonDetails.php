<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Services\PersonService\PersonConfig\PersonTypes;
use Faker\Factory;

class PersonDetails
{
    private $faker;

    private ?object $dateProvider;

    public function __construct(?object $dateProvider = null)
    {
        $this->faker = Factory::create();
        $this->dateProvider = $dateProvider;
    }

    public function setPersonDetails(string $type): PersonInfo
    {
        switch ($type) {
            case PersonTypes::PLAYER:
                $startDate = '-40 years';
                $endDate   = '-16 years';
                break;
            case PersonTypes::MANAGER:
                $startDate = '-65 years';
                $endDate = '-28 years';
                break;
            default:
                $startDate = '-40 years';
                $endDate   = '-16 years';
        }

        $dob = ($this->dateProvider ?? $this->faker)->dateTimeBetween($startDate, $endDate);
        $dob = date_format($dob, 'Y-m-d');

        return new PersonInfo(
            firstName: $this->faker->firstNameMale(),
            lastName: $this->faker->lastName(),
            countryCode: $this->faker->countryCode(),
            dateOfBirth: $dob
        );
    }
}
