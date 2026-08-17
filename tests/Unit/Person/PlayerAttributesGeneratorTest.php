<?php

namespace Tests\Unit\Person;

use App\Services\PersonService\Data\PersonInfo;
use App\Services\PersonService\GeneratePeople\PersonDetailsGenerator;
use App\Services\PersonService\GeneratePeople\PlayerAttributesGenerator;
use App\Services\PersonService\GeneratePeople\PlayerInitialAttributes;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

class PlayerAttributesGeneratorTest extends TestCase
{
    private PlayerAttributesGenerator $playerAttributesGenerator;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_generate_attributes()
    {
        $player = new stdClass;
        $player->position = 'striker';
        $player->potentialByCategory = (object) [
            'technical' => 80,
            'mental' => 75,
            'physical' => 85,
        ];
        $player->potential = 90;

        $playerInitialAttributes = $this->createPlayerInitialAttributesMock($player);

        $this->playerAttributesGenerator = new PlayerAttributesGenerator($playerInitialAttributes, new PersonDetailsGenerator);
        $this->playerAttributesGenerator->setPlayerDetails($player);

        $result = $this->playerAttributesGenerator->generateAttributes();

        $this->assertEquals('striker', $result->position);
        $this->assertEquals($player->potentialByCategory, $result->potentialByCategory);
        $this->assertEquals(90, $result->max_potential);
        $this->assertIsArray($result->positions);
        $this->assertContains('striker', $result->positions);
        $this->assertNotEmpty($result->personDetails->firstName);
        $this->assertNotEmpty($result->personDetails->lastName);
        $this->assertNotEmpty($result->personDetails->countryCode);

        $dob = Carbon::parse($result->personDetails->dateOfBirth);
        $this->assertTrue($dob->age >= 16 && $dob->age <= 40);
        $this->assertObjectHasProperty('potential', $result);
    }

    #[DataProvider('ageMaxPotentialProvider')]
    public function test_current_potential_for_different_ages(int $age, float $expectedMultiplier)
    {
        $player = new stdClass;
        $player->position = 'CB';
        $player->potentialByCategory = (object) ['technical' => 80];
        $player->potential = 100;

        $playerInitialAttributesMock = $this->createPlayerInitialAttributesMock($player);
        $mockDob = new \DateTime(date('Y') - $age.'-01-01');
        $personDetailsGenerator = $this->createMock(PersonDetailsGenerator::class);
        $personDetailsGenerator->expects($this->once())
            ->method('generate')
            ->willReturn(new PersonInfo('Test', 'Player', 'GB', $mockDob->format('Y-m-d')));

        $generator = new PlayerAttributesGenerator($playerInitialAttributesMock, $personDetailsGenerator);

        $generator->setPlayerDetails($player);
        $generatedPlayer = $generator->generateAttributes();

        $message = "Age $age should have potential of ".($generatedPlayer->max_potential * $expectedMultiplier);
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
            '41 years old' => [41, 0.67],
        ];
    }

    public function test_set_person_info_generates_valid_data()
    {
        $player = new stdClass;
        $player->position = 'striker';
        $player->potentialByCategory = null;
        $player->potential = 100;
        $playerInitialAttributes = $this->createPlayerInitialAttributesMock($player);
        $generator = new PlayerAttributesGenerator($playerInitialAttributes, new PersonDetailsGenerator);

        $generator->setPlayerDetails($player);
        $generatorReflection = new \ReflectionObject($generator);
        $player = $generatorReflection->getProperty('player');
        $player = $player->getValue($generator);

        $this->assertIsString($player->personDetails->firstName);
        $this->assertIsString($player->personDetails->lastName);
        $this->assertIsString($player->personDetails->countryCode);

        $dob = Carbon::parse($player->personDetails->dateOfBirth);
        $this->assertTrue($dob->age >= 16 && $dob->age <= 40, "Age should be between 16 and 40 but was {$dob->age}");
    }

    private function createPlayerInitialAttributesMock(stdClass $playerDetails)
    {
        $initialAttributesMock = $this->createMock(PlayerInitialAttributes::class);

        $initialAttributesMock->expects($this->any())
            ->method('setPlayerPosition')
            ->with($playerDetails->position)
            ->willReturn($initialAttributesMock);

        $initialAttributesMock->expects($this->any())
            ->method('setPlayerPotentialByCategory')
            ->with((array) $playerDetails->potentialByCategory)
            ->willReturn($initialAttributesMock);

        return $initialAttributesMock;
    }
}
