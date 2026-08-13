<?php

namespace App\Repositories;

use App\Models\Club;
use App\Models\Instance;
use App\Models\StaffCoaching;
use App\Models\StaffPhysio;
use App\Models\StaffScout;
use App\Services\PersonService\GeneratePeople\GeneratedStaffData;
use App\Services\PersonService\GeneratePeople\StaffSalary;
use App\Services\PersonService\PersonConfig\PersonTypes;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class StaffRepository
{
    private const STAFF_MODELS = [
        StaffCoaching::class,
        StaffScout::class,
        StaffPhysio::class,
    ];

    public function __construct(private readonly StaffSalary $staffSalary) {}

    /**
     * @param  list<GeneratedStaffData>  $staffMembers
     */
    public function bulkStaffInsert(int $instanceId, ?Club $club, array $staffMembers): void
    {
        DB::transaction(function () use ($instanceId, $club, $staffMembers): void {
            $contractStart = $club ? Instance::query()->findOrFail($instanceId)->instance_date : null;
            foreach ($staffMembers as $staffMember) {
                $contractId = $contractStart
                    ? DB::table('staff_contracts')->insertGetId([
                        'contract_start' => $contractStart,
                        'contract_end' => Carbon::parse($contractStart)->addYears(rand(1, 3))->toDateString(),
                        'salary' => $this->staffSalary->estimatedSalaryForStaffRole(
                            $staffMember->role,
                            $staffMember->potential
                        ),
                        'signing_fee' => null,
                    ])
                    : null;
                $personId = DB::table('people')->insertGetId([
                    'instance_id' => $instanceId,
                    'first_name' => $staffMember->firstName,
                    'last_name' => $staffMember->lastName,
                    'dob' => $staffMember->dateOfBirth,
                    'country_code' => $staffMember->countryCode,
                ]);

                if (in_array($staffMember->role, PersonTypes::COACHING_ROLES, true)) {
                    $this->insertCoachingStaff($instanceId, $club?->id, $contractId, $personId, $staffMember);

                    continue;
                }

                if ($staffMember->role === PersonTypes::SCOUT) {
                    $this->insertScout($instanceId, $club?->id, $contractId, $personId, $staffMember);

                    continue;
                }

                $this->insertPhysio($instanceId, $club?->id, $contractId, $personId, $staffMember);
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
                null,
                $personId,
                $staffMember
            );
        });
    }

    public function staffEligibleForRetirement(int $instanceId, string $date): LazyCollection
    {
        return LazyCollection::make(function () use ($instanceId, $date) {
            foreach (self::STAFF_MODELS as $staffModel) {
                yield from $staffModel::query()
                    ->forInstance($instanceId)
                    ->active()
                    ->whereHas('person', function ($query) use ($date): void {
                        $query->whereNotNull('dob')->whereDate('dob', '<=', $date);
                    })
                    ->with('person')
                    ->lazyById(200);
            }
        });
    }

    /** @return array<string, int> */
    public function activeStaffCountByRole(int $instanceId): array
    {
        $coachingRoles = StaffCoaching::query()
            ->forInstance($instanceId)
            ->active()
            ->selectRaw('type, COUNT(*) AS aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type')
            ->map(fn ($count): int => (int) $count)
            ->all();

        return array_merge($coachingRoles, [
            PersonTypes::SCOUT => StaffScout::query()->forInstance($instanceId)->active()->count(),
            PersonTypes::PHYSIO => StaffPhysio::query()->forInstance($instanceId)->active()
                ->where('team_type', 'FIRST_TEAM')->count(),
            PersonTypes::YOUTH_PHYSIO => StaffPhysio::query()->forInstance($instanceId)->active()
                ->where('team_type', 'YOUTH_TEAM')->count(),
        ]);
    }

    public function retireStaff(StaffCoaching|StaffScout|StaffPhysio $staff): bool
    {
        return DB::transaction(function () use ($staff): bool {
            $staff = $staff::query()->lockForUpdate()->findOrFail($staff->id);

            if ($staff->is_retired) {
                return false;
            }

            $contractId = $staff->contract_id;

            $staff->forceFill([
                'is_retired' => true,
                'club_id' => null,
                'contract_id' => null,
            ])->save();

            if ($contractId !== null) {
                DB::table('staff_contracts')->where('id', $contractId)->delete();
            }

            return true;
        });
    }

    private function insertCoachingStaff(int $instanceId, ?int $clubId, ?int $contractId, int $personId, GeneratedStaffData $staff): void
    {
        DB::table('staff_coaching')->insert(array_merge([
            'instance_id' => $instanceId,
            'person_id' => $personId,
            'club_id' => $clubId,
            'contract_id' => $contractId,
            'type' => $staff->role,
            'coaching_potential' => $staff->potential,
            'mental_potential' => $staff->potential,
            'goalkeeping_potential' => $staff->potential,
            'knowledge_potential' => $staff->potential,
        ], $staff->attributes));
    }

    private function insertScout(int $instanceId, ?int $clubId, ?int $contractId, int $personId, GeneratedStaffData $staff): void
    {
        DB::table('staff_scouts')->insert(array_merge([
            'instance_id' => $instanceId,
            'person_id' => $personId,
            'club_id' => $clubId,
            'contract_id' => $contractId,
        ], $staff->attributes));
    }

    private function insertPhysio(int $instanceId, ?int $clubId, ?int $contractId, int $personId, GeneratedStaffData $staff): void
    {
        DB::table('staff_physio')->insert(array_merge([
            'instance_id' => $instanceId,
            'person_id' => $personId,
            'club_id' => $clubId,
            'contract_id' => $contractId,
            'team_type' => $staff->role === PersonTypes::YOUTH_PHYSIO ? 'YOUTH_TEAM' : 'FIRST_TEAM',
        ], $staff->attributes));
    }
}
