<?php

namespace Tests\Unit\Domain\PlayerDevelopment;

use App\Domain\PlayerDevelopment\PlayerAttributeCeiling;
use Tests\TestCase;

class PlayerAttributeCeilingTest extends TestCase
{
    public function test_it_floors_category_potential_to_an_attribute_ceiling(): void
    {
        $ceiling = new PlayerAttributeCeiling;

        $this->assertSame(8, $ceiling->forPotential(87));
        $this->assertSame(20, $ceiling->forPotential(250));
        $this->assertSame(0, $ceiling->forPotential(-1));
    }
}
