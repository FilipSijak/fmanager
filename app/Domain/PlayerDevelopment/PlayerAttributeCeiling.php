<?php

namespace App\Domain\PlayerDevelopment;

class PlayerAttributeCeiling
{
    public function forPotential(int $currentCategoryPotential): int
    {
        return min(20, intdiv(max(0, $currentCategoryPotential), 10));
    }
}
