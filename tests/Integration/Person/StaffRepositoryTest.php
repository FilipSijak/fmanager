<?php

namespace Tests\Integration\Person;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Person;
use App\Repositories\StaffRepository;
use App\Services\PersonService\GeneratePeople\GeneratedStaffData;
use App\Services\PersonService\GeneratePeople\StaffGenerator;
use App\Services\PersonService\PersonConfig\PersonTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_club_staff_with_contracts_and_free_staff_without_them(): void
    {
        $instance = Instance::factory()->create([
            'id' => 1,
            'instance_date' => '2026-07-01',
        ]);
        $club = Club::factory()->create([
            'instance_id' => $instance->id,
            'rank' => 15,
        ]);
        $generatedStaff = app(StaffGenerator::class)->generateForClubRank($club->rank);
        $staff = [
            $this->staffWithRole($generatedStaff, PersonTypes::MANAGER),
            $this->staffWithRole($generatedStaff, PersonTypes::SCOUT),
            $this->staffWithRole($generatedStaff, PersonTypes::PHYSIO),
        ];
        $repository = app(StaffRepository::class);

        $repository->bulkStaffInsert($instance->id, $club, $staff);
        $repository->bulkStaffInsert($instance->id, null, $staff);

        foreach (['staff_coaching', 'staff_scouts', 'staff_physio'] as $table) {
            $this->assertDatabaseHas($table, [
                'instance_id' => $instance->id,
                'club_id' => $club->id,
                'contract_start' => '2026-07-01',
            ]);
            $this->assertDatabaseMissing($table, [
                'instance_id' => $instance->id,
                'club_id' => $club->id,
                'contract_end' => null,
            ]);
            $this->assertDatabaseHas($table, [
                'instance_id' => $instance->id,
                'club_id' => null,
                'contract_start' => null,
                'contract_end' => null,
            ]);
        }

        $this->assertDatabaseHas('staff_coaching', ['type' => PersonTypes::MANAGER]);
        $this->assertDatabaseHas('staff_physio', ['team_type' => 'FIRST_TEAM']);
        $this->assertDatabaseCount('people', 6);
    }

    #[Test]
    public function it_creates_a_free_coaching_career_for_an_existing_person(): void
    {
        $instance = Instance::factory()->create(['id' => 1]);
        $person = Person::factory()->create(['instance_id' => $instance->id]);
        $staff = app(StaffGenerator::class)->generateFromFormerPlayer($person);

        app(StaffRepository::class)->insertForExistingPerson(
            $instance->id,
            $person->id,
            $staff
        );

        $this->assertDatabaseCount('people', 1);
        $this->assertDatabaseHas('staff_coaching', [
            'instance_id' => $instance->id,
            'person_id' => $person->id,
            'club_id' => null,
            'type' => $staff->role,
            'contract_start' => null,
            'contract_end' => null,
        ]);
    }

    /** @param list<GeneratedStaffData> $staff */
    private function staffWithRole(array $staff, string $role): GeneratedStaffData
    {
        $staffMember = array_find(
            $staff,
            fn (GeneratedStaffData $staffMember): bool => $staffMember->role === $role
        );

        $this->assertNotNull($staffMember);

        return $staffMember;
    }
}
