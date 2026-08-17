<?php

namespace App\Services\PersonService\Data;

readonly class PersonInfo
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $countryCode,
        public ?string $dateOfBirth,
    ) {}
}
