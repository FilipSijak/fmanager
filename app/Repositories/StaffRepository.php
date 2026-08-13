<?php

namespace App\Repositories;

use App\Models\Club;
use App\Models\Instance;
use App\Services\PersonService\GeneratePeople\GeneratedStaffData;
use App\Services\PersonService\PersonConfig\PersonTypes;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StaffRepository
{
    /**
     * @param  list<GeneratedStaffData>  $staffMembers
     */
    public function bulkStaffInsert(int $instanceId, ?Club $club, array $staffMembers): void
    {
        DB::transaction(function () use ($instanceId, $club, $staffMembers): void {
            $contractStart = $club ? Instance::query()->findOrFail($instanceId)->instance_date : null;
            foreach ($staffMembers as $staffMember) {
                $contract = [
                    'contract_start' => $contractStart,
                    'contract_end' => $contractStart
                        ? Carbon::parse($contractStart)->addYears(rand(1, 3))->toDateString()
                        : null,
                ];
                $personId = DB::table('people')->insertGetId([
                    'instance_id' => $instanceId,
                    'first_name' => $staffMember->firstName,
                    'last_name' => $staffMember->lastName,
                    'dob' => $staffMember->dateOfBirth,
                    'country_code' => $staffMember->countryCode,
                ]);

                if (in_array($staffMember->role, PersonTypes::COACHING_ROLES, true)) {
                    $this->insertCoachingStaff($instanceId, $club?->id, $personId, $staffMember, $contract);

                    continue;
                }

                if ($staffMember->role === PersonTypes::SCOUT) {
                    $this->insertScout($instanceId, $club?->id, $personId, $staffMember, $contract);

                    continue;
                }

                $this->insertPhysio($instanceId, $club?->id, $personId, $staffMember, $contract);
            }
        });
    }

    public function insertForExistingPerson(
        int $instanceId,
        int $personId,
        GeneratedStaffData $staffMember
    ): void {
        DB::transaction(function () use ($instanceId, $personId, $staffMember): void {
            $this->insertCoachingStaff(
                $instanceId,
                null,
                $personId,
                $staffMember,
                ['contract_start' => null, 'contract_end' => null]
            );
        });
    }

    private function insertCoachingStaff(int $instanceId, ?int $clubId, int $personId, GeneratedStaffData $staff, array $contract): void
    {
        DB::table('staff_coaching')->insert(array_merge([
            'instance_id' => $instanceId,
            'person_id' => $personId,
            'club_id' => $clubId,
            'type' => $staff->role,
            'coaching_potential' => $staff->potential,
            'mental_potential' => $staff->potential,
            'goalkeeping_potential' => $staff->potential,
            'knowledge_potential' => $staff->potential,
        ], $contract, $staff->attributes));
    }

    private function insertScout(int $instanceId, ?int $clubId, int $personId, GeneratedStaffData $staff, array $contract): void
    {
        DB::table('staff_scouts')->insert(array_merge([
            'instance_id' => $instanceId,
            'person_id' => $personId,
            'club_id' => $clubId,
        ], $contract, $staff->attributes));
    }

    private function insertPhysio(int $instanceId, ?int $clubId, int $personId, GeneratedStaffData $staff, array $contract): void
    {
        DB::table('staff_physio')->insert(array_merge([
            'instance_id' => $instanceId,
            'person_id' => $personId,
            'club_id' => $clubId,
            'team_type' => $staff->role === PersonTypes::YOUTH_PHYSIO ? 'YOUTH_TEAM' : 'FIRST_TEAM',
        ], $contract, $staff->attributes));
    }
}
