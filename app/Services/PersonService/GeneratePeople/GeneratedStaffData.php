<?php

namespace App\Services\PersonService\GeneratePeople;

readonly class GeneratedStaffData
{
    public function __construct(
        public string $role,
        public int $potential,
        public int $rank,
        public string $firstName,
        public string $lastName,
        public string $dateOfBirth,
        public string $countryCode,
        /** @var array<string, int> */
        public array $attributes,
    ) {}
}
