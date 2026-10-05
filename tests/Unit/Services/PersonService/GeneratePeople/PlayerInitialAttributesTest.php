<?php

namespace Tests\Unit\Services\PersonService\GeneratePeople;

use App\Services\PersonService\GeneratePeople\PlayerInitialAttributes;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
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

    public function test_goalkeepers_have_lower_outfield_technical_attributes_without_reducing_goalkeeping_skills(): void
    {
        $potentialByCategory = [
            'technical' => 180,
            'mental' => 180,
            'physical' => 180,
        ];

        $outfieldAttributes = (new PlayerInitialAttributes(new Randomizer(new Xoshiro256StarStar(1234))))
            ->setPlayerPosition('CB')
            ->setPlayerPotentialByCategory($potentialByCategory)
            ->initAllAttributes();

        $goalkeeperAttributes = (new PlayerInitialAttributes(new Randomizer(new Xoshiro256StarStar(1234))))
            ->setPlayerPosition('GK')
            ->setPlayerPotentialByCategory($potentialByCategory)
            ->initAllAttributes();

        foreach (PlayerFields::TECHNICAL_FIELDS as $field) {
            $this->assertLessThan($outfieldAttributes[$field], $goalkeeperAttributes[$field], $field);
        }

        $this->assertGreaterThanOrEqual(17, $goalkeeperAttributes['handling']);
        $this->assertGreaterThanOrEqual(17, $goalkeeperAttributes['positioning']);
        $this->assertGreaterThanOrEqual(17, $goalkeeperAttributes['agility']);
    }

    public function test_goalkeeper_outfield_technical_attributes_do_not_fall_below_one(): void
    {
        $attributes = (new PlayerInitialAttributes(new Randomizer(new Xoshiro256StarStar(1234))))
            ->setPlayerPosition('GK')
            ->setPlayerPotentialByCategory([
                'technical' => 30,
                'mental' => 30,
                'physical' => 30,
            ])
            ->initAllAttributes();

        foreach (PlayerFields::TECHNICAL_FIELDS as $field) {
            $this->assertSame(1, $attributes[$field], $field);
        }
    }
}
