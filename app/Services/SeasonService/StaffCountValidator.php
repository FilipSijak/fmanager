<?php

namespace App\Services\SeasonService;

use App\Models\Club;
use App\Models\Instance;
use App\Repositories\StaffRepository;
use App\Services\ClubService\SquadAnalysis\SquadStaffConfig;
use App\Services\PersonService\PersonConfig\PersonTypes;

class StaffCountValidator
{
    private const INITIAL_FREE_STAFF_PERCENTAGE = 20;

    public function __construct(private readonly StaffRepository $staffRepository) {}

    /** @return array<string, int> */
    public function missingStaffByRole(Instance $instance): array
    {
        $clubCount = Club::query()->forInstance($instance->id)->count();
        $requiredPerClub = $this->staffRequiredPerClubByRole();
        $initialFreeStaffPerClub = (int) round(
            array_sum($requiredPerClub) * self::INITIAL_FREE_STAFF_PERCENTAGE
                / (100 - self::INITIAL_FREE_STAFF_PERCENTAGE)
        );
        $initialFreeStaffByRole = $this->distributeInitialFreeStaffByRole(
            $requiredPerClub,
            $initialFreeStaffPerClub
        );
        $activeStaffByRole = $this->staffRepository->activeStaffCountByRole((int) $instance->id);
        $missingStaffByRole = [];

        foreach ($requiredPerClub as $role => $requiredCount) {
            $target = $clubCount * ($requiredCount + $initialFreeStaffByRole[$role]);
            $missingStaffByRole[$role] = max(0, $target - ($activeStaffByRole[$role] ?? 0));
        }

        return $missingStaffByRole;
    }

    /** @return array<string, int> */
    private function staffRequiredPerClubByRole(): array
    {
        return [
            PersonTypes::MANAGER => SquadStaffConfig::MANAGER_COUNT,
            PersonTypes::ASSISTANT_MANAGER => SquadStaffConfig::ASSISTANT_MANAGER_COUNT,
            PersonTypes::COACH => SquadStaffConfig::FIRST_TEAM_COACH_COUNT,
            PersonTypes::YOUTH_COACH => SquadStaffConfig::YOUTH_TEAM_COACH_COUNT,
            PersonTypes::SCOUT => SquadStaffConfig::SCOUT_COUNT,
            PersonTypes::PHYSIO => SquadStaffConfig::PHYSIO_FIRST_TEAM_COUNT,
            PersonTypes::YOUTH_PHYSIO => SquadStaffConfig::PHYSIO_YOUTH_TEAM_COUNT,
        ];
    }

    /**
     * @param  array<string, int>  $requiredPerClub
     * @return array<string, int>
     */
    private function distributeInitialFreeStaffByRole(array $requiredPerClub, int $freeStaffCount): array
    {
        $totalRequired = array_sum($requiredPerClub);
        $freeStaffByRole = [];
        $remainders = [];

        foreach ($requiredPerClub as $role => $requiredCount) {
            $share = $requiredCount * $freeStaffCount / $totalRequired;
            $freeStaffByRole[$role] = (int) floor($share);
            $remainders[$role] = $share - $freeStaffByRole[$role];
        }

        arsort($remainders);
        $remaining = $freeStaffCount - array_sum($freeStaffByRole);

        foreach (array_keys($remainders) as $role) {
            if ($remaining === 0) {
                break;
            }

            $freeStaffByRole[$role]++;
            $remaining--;
        }

        return $freeStaffByRole;
    }
}
