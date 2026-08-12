<?php

namespace App\Services\PersonService\GeneratePeople;

readonly class GeneratedStaffData
{
    public function __construct(
        public string $role,
        public int $potential,
        public string $firstName,
        public string $lastName,
        public string $dateOfBirth,
        public string $countryCode,
        /**  array<string, int> */
        public array $attributes,
    ) {}
}
