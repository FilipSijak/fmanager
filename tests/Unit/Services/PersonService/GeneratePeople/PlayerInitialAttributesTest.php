<?php

namespace Tests\Unit\Services\PersonService\GeneratePeople;

use App\Services\PersonService\GeneratePeople\PlayerInitialAttributes;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

class PlayerInitialAttributesTest extends TestCase
{
    public function test_unprioritized_attributes_use_their_own_category_potential(): void
    {
        $attributes = (new PlayerInitialAttributes(new Randomizer(new Xoshiro256StarStar(1234))))
            ->setPlayerPosition('CB')
            ->setPlayerPotentialByCategory([
                'technical' => 30,
                'mental' => 180,
                'physical' => 100,
            ])
            ->initAllAttributes();

        $this->assertSame(3, $attributes['corners']);
        $this->assertGreaterThanOrEqual(8, $attributes['creativity']);
        $this->assertGreaterThanOrEqual(5, $attributes['pace']);
        $this->assertGreaterThanOrEqual(17, $attributes['positioning']);
        $this->assertSame(1, $attributes['handling']);
    }
}
