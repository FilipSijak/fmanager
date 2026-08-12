<?php

namespace App\Services\PersonService\GeneratePeople;

readonly class StaffPotentialData
{
    public function __construct(
        public string $role,
        public int $potential,
    ) {}
}
