<?php

namespace Tests\Unit\Services\PersonService\GeneratePeople;

use App\Services\PersonService\GeneratePeople\PlayerAttributesGenerator;
use App\Services\PersonService\GeneratePeople\PlayerInitialAttributes;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use stdClass;

class PlayerAttributesGeneratorTest extends TestCase
{
    private PlayerAttributesGenerator $playerAttributesGenerator;
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testGenerateAttributes()
    {
        $player = new stdClass();
        $player->position = 'striker';
        $player->potentialByCategory = (object)[
            'technical' => 80,
            'mental' => 75,
            'physical' => 85
        ];
        $player->potential = 90;

        $playerInitialAttributes = $this->createPlayerInitialAttributesMock($player);

        $this->playerAttributesGenerator = new PlayerAttributesGenerator($playerInitialAttributes);

        $result = $this->playerAttributesGenerator->generateAttributes($player);

        $this->assertEquals('striker', $result->position);
        $this->assertEquals($player->potentialByCategory, $result->potentialByCategory);
        $this->assertEquals(90, $result->max_potential);
        $this->assertIsArray($result->positions);
        $this->assertContains('striker', $result->positions);
        $this->assertNotEmpty($result->first_name);
        $this->assertNotEmpty($result->last_name);
        $this->assertNotEmpty($result->country_code);

        $dob = Carbon::parse($result->dob);
        $this->assertTrue($dob->age >= 16 && $dob->age <= 40);
        $this->assertObjectHasProperty('potential', $result);
    }

    /**
     * @dataProvider ageMaxPotentialProvider
     */
    public function testSetMaxPotentialForDifferentAges(int $age, float $expectedMultiplier)
    {
        $player = new stdClass();
        $player->position = 'striker';
        $player->potentialByCategory = (object)['technical' => 80];
        $player->potential = 100;

        $reflectionClass = new \ReflectionClass(PlayerAttributesGenerator::class);
        $setMaxPotentialMethod = $reflectionClass->getMethod('setMaxPotential');
        $setMaxPotentialMethod->setAccessible(true);

        $playerInitialAttributes = $this->createPlayerInitialAttributesMock($player);

        $generator = new PlayerAttributesGenerator($playerInitialAttributes);
        $generator->player = new stdClass();
        $generator->player->dob = Carbon::now()->subYears($age)->format('Y-m-d');
        $generator->player->max_potential = 100;

        $setMaxPotentialMethod->invoke($generator);

        $message = "Age $age should have potential of " . ($generator->player->max_potential * $expectedMultiplier);
        $this->assertEquals($generator->player->max_potential * $expectedMultiplier, $generator->player->potential, $message);
    }

    public static function ageMaxPotentialProvider(): array
    {
        return [
            '16 years old' => [16, 0.85],
            '18 years old' => [18, 0.90],
            '21 years old' => [21, 0.95],
            '24 years old' => [24, 1.00],
            '29 years old' => [29, 0.98],
            '30 years old' => [30, 0.95]
        ];
    }

    public function testSetPersonInfoGeneratesValidData()
    {
        // Create a reflection to access protected method
        $reflectionClass = new \ReflectionClass(PlayerAttributesGenerator::class);
        $setPersonInfoMethod = $reflectionClass->getMethod('setPersonInfo');
        $setPersonInfoMethod->setAccessible(true);

        // Create an instance and set up the player property
        $player = new stdClass();
        $player->position = 'striker';
        $player->potentialByCategory = null;
        $playerInitialAttributes = $this->createPlayerInitialAttributesMock($player);
        $generator = new PlayerAttributesGenerator($playerInitialAttributes);
        $generator->player = new stdClass();

        $setPersonInfoMethod->invoke($generator);

        $this->assertIsString($generator->player->first_name);
        $this->assertIsString($generator->player->last_name);
        $this->assertIsString($generator->player->country_code);

        $dob = Carbon::parse($generator->player->dob);
        $this->assertTrue($dob->age >= 16 && $dob->age <= 40, "Age should be between 16 and 40 but was {$dob->age}");
    }

    private function createPlayerInitialAttributesMock(\stdClass $playerDetails)
    {
        $initialAttributesMock = $this->createMock(PlayerInitialAttributes::class);

        $initialAttributesMock->expects($this->any())
                                          ->method('setPlayerPosition')
                                          ->with($playerDetails->position)
                                          ->willReturn($initialAttributesMock);

        $initialAttributesMock->expects($this->any())
                                          ->method('setPlayerPotentialByCategory')
                                          ->with((array)$playerDetails->potentialByCategory)
                                          ->willReturn($initialAttributesMock);

        return $initialAttributesMock;
    }
}
