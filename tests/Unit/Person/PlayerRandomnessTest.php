<?php

namespace Tests\Unit\Person;

use App\Services\PersonService\GeneratePeople\PlayerInitialAttributes;
use App\Services\PersonService\GeneratePeople\PlayerPotential;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

class PlayerRandomnessTest extends TestCase
{
    public function test_player_profiles_are_repeatable_with_a_seeded_randomizer(): void
    {
        $firstGenerator = new PlayerPotential(new Randomizer(new Xoshiro256StarStar(1234)));
        $secondGenerator = new PlayerPotential(new Randomizer(new Xoshiro256StarStar(1234)));

        $this->assertEquals(
            $firstGenerator->createForClubRank(15),
            $secondGenerator->createForClubRank(15),
        );

        $freeAgent = $firstGenerator->createFreeAgent(150);
        $this->assertContains($freeAgent->position, PlayerPositionConfig::PLAYER_POSITIONS);
    }

    public function test_player_attributes_are_repeatable_with_a_seeded_randomizer(): void
    {
        $firstGenerator = new PlayerInitialAttributes(new Randomizer(new Xoshiro256StarStar(5678)));
        $secondGenerator = new PlayerInitialAttributes(new Randomizer(new Xoshiro256StarStar(5678)));
        $potential = ['technical' => 100, 'mental' => 100, 'physical' => 100];

        $firstAttributes = $firstGenerator
            ->setPlayerPosition('ST')
            ->setPlayerPotentialByCategory($potential)
            ->initAllAttributes();
        $secondAttributes = $secondGenerator
            ->setPlayerPosition('ST')
            ->setPlayerPotentialByCategory($potential)
            ->initAllAttributes();

        $this->assertSame($firstAttributes, $secondAttributes);

        $lowPotentialAttributes = (new PlayerInitialAttributes(new Randomizer(new Xoshiro256StarStar(42))))
            ->setPlayerPosition('ST')
            ->setPlayerPotentialByCategory(['technical' => 30, 'mental' => 30, 'physical' => 30])
            ->initAllAttributes();

        $this->assertNotEmpty($lowPotentialAttributes);
    }
}
