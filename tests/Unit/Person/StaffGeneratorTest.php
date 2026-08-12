<?php

namespace Tests\Unit\Person;

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
}
