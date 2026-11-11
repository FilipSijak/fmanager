<?php

namespace App\Services\PersonService;

use App\Models\Club;
use App\Models\StaffCoaching;
use App\Models\User;
use App\Repositories\StaffRepository;
use App\Services\ContractService\ContractService;
use App\Services\PersonService\GeneratePeople\StaffType\GeneratedStaffData;
use App\Services\PersonService\GeneratePeople\StaffType\StaffCreator;
use App\Services\PersonService\PersonConfig\PersonTypes;
use App\Support\GameContext;
use Illuminate\Support\Facades\DB;

class PersonService
{
    public function __construct(
        private readonly StaffRepository $staffRepository,
        private readonly ContractService $contracts,
        private readonly StaffCreator $staffCreator,
        private readonly GameContext $gameContext,
    ) {}

    /** @param list<GeneratedStaffData> $staffMembers */
    private function persistGeneratedStaff(?Club $club, array $staffMembers): void
    {
        DB::transaction(function () use ($club, $staffMembers): void {
            $instanceId = $this->gameContext->instanceId();
            $contractIds = $this->contracts->createInitialStaffContracts($instanceId, $club, $staffMembers);
            $this->staffRepository->bulkStaffInsert($instanceId, $club, $staffMembers, $contractIds);
        });
    }

    public function initialStaffClubSeed(Club $club): void
    {
        $staffMembers = $this->staffCreator->generateForClubRank($club->rank);

        $this->persistGeneratedStaff($club, $staffMembers);
    }

    public function assignUserManager(Club $club, User $user): StaffCoaching
    {
        return DB::transaction(function () use ($club, $user): StaffCoaching {
            $previousManager = StaffCoaching::query()
                ->forInstance($club->instance_id)
                ->where('club_id', $club->id)
                ->where('type', PersonTypes::MANAGER)
                ->active()
                ->lockForUpdate()
                ->firstOrFail();

            $nameParts = explode(' ', trim($user->name), 2);
            $person = $previousManager->person->replicate();
            $person->first_name = $nameParts[0];
            $person->last_name = $nameParts[1] ?? '';
            $person->save();

            $manager = $previousManager->replicate();
            $manager->setRelations([]);
            $manager->person_id = $person->id;
            $manager->reputation = $club->managerReputationTarget();

            foreach ([
                'attacking', 'defending', 'fitness', 'mental', 'tactical', 'technical',
                'working_with_youngsters', 'adaptability', 'determination', 'discipline',
                'man_management', 'motivating', 'judging_player_potential', 'judging_player_ability',
                'judging_staff_ability', 'negotiating', 'tactics', 'distribution', 'handling', 'shot_stopping',
            ] as $attribute) {
                $manager->{$attribute} = 1;
            }

            $manager->save();

            if ($previousManager->tacticPreference !== null) {
                $preference = $previousManager->tacticPreference->replicate();
                $preference->staff_coaching_id = $manager->id;
                $preference->save();
            }

            $previousManager->club_id = null;
            $previousManager->contract_id = null;
            $previousManager->save();

            return $manager;
        });
    }

    /**
     * Free the user's manager from their club and end the contract, leaving the club vacant for the next appointment.
     */
    public function releaseUserManager(StaffCoaching $userManager): void
    {
        DB::transaction(function () use ($userManager): void {
            $contractId = $userManager->contract_id;
            $userManager->club_id = null;
            $userManager->contract_id = null;
            $userManager->save();

            if ($contractId !== null) {
                DB::table('staff_contracts')->where('id', $contractId)->delete();
            }
        });
    }

    public function generateFreeStaff(int $count): void
    {
        $freeStaff = $this->staffCreator->generateFreeStaff($count);

        $this->persistGeneratedStaff(null, $freeStaff);
    }
}
