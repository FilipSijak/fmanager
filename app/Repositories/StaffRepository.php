<?php

namespace App\Repositories;

use App\Models\Club;
use App\Services\PersonService\PersonConfig\PersonTypes;
use Illuminate\Support\Facades\DB;

class StaffRepository
{
    public function bulkStaffInsert(int $instanceId, Club $club, array $staffMembers): void
    {
        DB::transaction(function () use ($instanceId, $club, $staffMembers): void {
            foreach ($staffMembers as $staffMember) {
                $personId = DB::table('people')->insertGetId([
                    'instance_id' => $instanceId,
                    'first_name' => $staffMember->first_name,
                    'last_name' => $staffMember->last_name,
                    'dob' => $staffMember->dob,
                    'country_code' => $staffMember->country_code,
                ]);

                if (in_array($staffMember->role, PersonTypes::COACHING_ROLES, true)) {
                    $this->insertCoachingStaff($instanceId, $club->id, $personId, $staffMember);

                    continue;
                }

                if ($staffMember->role === PersonTypes::SCOUT) {
                    $this->insertScout($instanceId, $club->id, $personId, $staffMember);

                    continue;
                }

                $this->insertPhysio($instanceId, $club->id, $personId, $staffMember);
            }
        });
    }

    private function insertCoachingStaff(int $instanceId, int $clubId, int $personId, \stdClass $staff): void
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
        ], $staff->attributes));
    }

    private function insertScout(int $instanceId, int $clubId, int $personId, \stdClass $staff): void
    {
        DB::table('staff_scouts')->insert(array_merge([
            'instance_id' => $instanceId,
            'person_id' => $personId,
            'club_id' => $clubId,
        ], $staff->attributes));
    }

    private function insertPhysio(int $instanceId, int $clubId, int $personId, \stdClass $staff): void
    {
        DB::table('staff_physio')->insert(array_merge([
            'instance_id' => $instanceId,
            'person_id' => $personId,
            'club_id' => $clubId,
            'team_type' => $staff->role === PersonTypes::YOUTH_PHYSIO ? 'YOUTH_TEAM' : 'FIRST_TEAM',
        ], $staff->attributes));
    }
}
