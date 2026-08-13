<?php

namespace Tests\Unit\Person;

use App\Models\Person;
use App\Services\PersonService\GeneratePeople\GeneratedStaffData;
use App\Services\PersonService\GeneratePeople\StaffGenerator;
use App\Services\PersonService\PersonConfig\PersonTypes;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffGeneratorTest extends TestCase
{
    #[Test]
    public function it_generates_typed_staff_with_identity_and_role_attributes(): void
    {
        $staff = app(StaffGenerator::class)->generateForClubRank(12);

        $this->assertCount(20, $staff);

        foreach ($staff as $staffMember) {
            $this->assertInstanceOf(GeneratedStaffData::class, $staffMember);
            $this->assertSame(12, $staffMember->rank);
            $this->assertNotEmpty($staffMember->firstName);
            $this->assertNotEmpty($staffMember->lastName);
            $this->assertMatchesRegularExpression("/^\d{4}-\d{2}-\d{2}$/", $staffMember->dateOfBirth);
            $this->assertNotEmpty($staffMember->countryCode);
            $this->assertNotEmpty($staffMember->attributes);

            foreach ($staffMember->attributes as $attribute) {
                $this->assertGreaterThanOrEqual(1, $attribute);
                $this->assertLessThanOrEqual(20, $attribute);
            }

            if (in_array($staffMember->role, PersonTypes::COACHING_ROLES, true)) {
                $this->assertSame([
                    'attacking', 'defending', 'fitness', 'mental', 'tactical', 'technical',
                    'working_with_youngsters', 'adaptability', 'determination', 'discipline',
                    'man_management', 'motivating', 'judging_player_potential',
                    'judging_player_ability', 'judging_staff_ability', 'negotiating', 'tactics',
                    'distribution', 'handling', 'shot_stopping',
                ], array_keys($staffMember->attributes));
            } elseif ($staffMember->role === PersonTypes::SCOUT) {
                $this->assertSame([
                    'judging_player_ability', 'judging_player_potential', 'tactical_knowledge',
                    'data_analysis', 'market_knowledge',
                ], array_keys($staffMember->attributes));
            } else {
                $this->assertSame([
                    'physiotherapy', 'injury_prevention', 'rehabilitation', 'sports_science',
                    'fitness_assessment',
                ], array_keys($staffMember->attributes));
            }
        }
    }

    #[Test]
    public function it_generates_the_requested_number_of_independently_ranked_free_staff(): void
    {
        $staff = app(StaffGenerator::class)->generateFreeStaff(100);

        $this->assertCount(100, $staff);
        $this->assertGreaterThan(1, count(array_unique(array_map(
            fn (GeneratedStaffData $staffMember): int => $staffMember->rank,
            $staff
        ))));
    }

    #[Test]
    public function it_reuses_a_former_players_identity_for_a_random_coaching_role(): void
    {
        $person = new Person([
            'first_name' => 'Former',
            'last_name' => 'Player',
            'dob' => '1988-04-12',
            'country_code' => 'GB',
        ]);

        $staff = app(StaffGenerator::class)->generateFromFormerPlayer($person);

        $this->assertContains($staff->role, [
            PersonTypes::MANAGER,
            PersonTypes::ASSISTANT_MANAGER,
            PersonTypes::COACH,
        ]);
        $this->assertSame('Former', $staff->firstName);
        $this->assertSame('Player', $staff->lastName);
        $this->assertSame('1988-04-12', $staff->dateOfBirth);
        $this->assertSame('GB', $staff->countryCode);
        $this->assertGreaterThanOrEqual(1, $staff->rank);
        $this->assertLessThanOrEqual(20, $staff->rank);
    }

    #[Test]
    public function it_generates_free_staff_for_a_specific_role(): void
    {
        $staff = app(StaffGenerator::class)->generateFreeStaffForRole(PersonTypes::SCOUT, 10);

        $this->assertCount(10, $staff);
        $this->assertSame([PersonTypes::SCOUT], array_values(array_unique(array_map(
            fn (GeneratedStaffData $staffMember): string => $staffMember->role,
            $staff
        ))));
    }
}
