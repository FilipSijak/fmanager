<?php

namespace App\Services\PersonService\GeneratePeople\StaffType;

use App\Services\PersonService\Data\PersonInfo;

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
}
