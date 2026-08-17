<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Services\PersonService\Data\PersonInfo;
use App\Services\PersonService\PersonConfig\PersonTypes;
use Faker\Factory;
use Faker\Generator;
use InvalidArgumentException;

class PersonDetailsGenerator
{
    private Generator $faker;

    public function __construct(?Generator $faker = null)
    {
        $this->faker = $faker ?? Factory::create();
    }

    public function generate(string $personType): PersonInfo
    {
        [$startDate, $endDate] = match ($personType) {
            PersonTypes::PLAYER => ['-40 years', '-16 years'],
            PersonTypes::MANAGER => ['-65 years', '-28 years'],
            default => throw new InvalidArgumentException('Unsupported person type: '.$personType),
        };

        return new PersonInfo(
            firstName: $this->faker->firstNameMale(),
            lastName: $this->faker->lastName(),
            countryCode: $this->faker->countryCode(),
            dateOfBirth: $this->faker->dateTimeBetween($startDate, $endDate)->format('Y-m-d'),
        );
    }
}
