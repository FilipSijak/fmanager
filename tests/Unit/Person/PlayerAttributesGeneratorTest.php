<?php

namespace Tests\Unit\Person;

use App\Services\PersonService\GeneratePeople\PlayerAttributesGenerator;
use App\Services\PersonService\GeneratePeople\PlayerInitialAttributes;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

interface FakerDateTimeExtendedInterface
{
    public function dateTimeBetween($startDate = '-30 years', $endDate = 'now', $timezone = null);
}


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
        $this->playerAttributesGenerator->setPlayerDetails($player);

        $result = $this->playerAttributesGenerator->generateAttributes();

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

    #[DataProvider('ageMaxPotentialProvider')]
    public function testCurrentPotentialForDifferentAges(int $age, float $expectedMultiplier)
    {
        $player = new stdClass();
        $player->position = 'CB';
        $player->potentialByCategory = (object)['technical' => 80];
        $player->potential = 100;

        $fakerMock = $this->getMockBuilder(FakerDateTimeExtendedInterface::class)
                          ->onlyMethods(['dateTimeBetween'])
                          ->getMock();
        $playerInitialAttributesMock = $this->createPlayerInitialAttributesMock($player);


        $mockDob = new \DateTime(date("Y") - $age .'-01-01');

        $fakerMock->expects($this->any())
                  ->method('dateTimeBetween')
                  ->with('-40 years', '-16 years')
                  ->willReturn($mockDob);

        // Create generator with the mock dependency
        $generator = new PlayerAttributesGenerator($playerInitialAttributesMock);
        $reflection    = new \ReflectionClass($generator);
        $fakerProperty = $reflection->getProperty('faker');
        $fakerProperty->setValue($generator, $fakerMock);

        // Call the method
        $generator->setPlayerDetails($player);
        $generatedPlayer = $generator->generateAttributes();


        $message = "Age $age should have potential of " . ($generatedPlayer->max_potential * $expectedMultiplier);
        $this->assertEquals($generatedPlayer->max_potential * $expectedMultiplier, $generatedPlayer->potential, $message);
    }

    public static function ageMaxPotentialProvider(): array
    {
        return [
            '16 years old' => [16, 0.85],
            '18 years old' => [18, 0.90],
            '21 years old' => [21, 0.95],
            '24 years old' => [24, 1.00],
            '29 years old' => [29, 0.98],
            '30 years old' => [30, 0.95],
            '32 years old' => [32, 0.92],
            '33 years old' => [33, 0.89],
            '35 years old' => [35, 0.83],
            '38 years old' => [38, 0.75],
            '41 years old' => [41, 0.67]
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

        $player = new \ReflectionProperty(PlayerAttributesGenerator::class, 'player');
        $player->setAccessible(true);

        $setPersonInfoMethod->invoke($generator);

        $this->assertIsString($player->first_name);
        $this->assertIsString($player->last_name);
        $this->assertIsString($player->country_code);

        $dob = Carbon::parse($player->dob);
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
