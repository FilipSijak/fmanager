<?php

namespace App\Services\PersonService\GeneratePeople\StaffType;

readonly class StaffPotentialData
{
    public function __construct(
        public string $role,
        public int $potential,
        public int $rank,
    ) {}
}
