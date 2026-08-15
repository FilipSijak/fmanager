<?php

namespace App\Services\PersonService\GeneratePeople\StaffType;

use App\Services\PersonService\GeneratePeople\PersonInfo;

readonly class GeneratedStaffData
{
    public function __construct(
        public string $role,
        public int $potential,
        public int $rank,
        public PersonInfo $personDetails,
        /** @var array<string, int> */
        public array $attributes,
    ) {}

    public function __get(string $name): mixed
    {
        return $this->personDetails->{$name};
    }
}
