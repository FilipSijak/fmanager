<?php

namespace App\Services\SeasonService;

use App\Models\Club;
use App\Models\Instance;
use App\Repositories\StaffRepository;
use App\Services\ClubService\SquadAnalysis\SquadStaffConfig;

class StaffCountValidator
{
    private const FREE_STAFF_PERCENTAGE = 20;

    public function __construct(private readonly StaffRepository $staffRepository) {}

    public function missingStaffCount(Instance $instance): int
    {
        $clubCount = Club::query()->forInstance($instance->id)->count();
        $requiredClubStaff = $clubCount * $this->staffRequiredPerClub();
        $requiredTotalStaff = (int) ceil(
            $requiredClubStaff / (1 - self::FREE_STAFF_PERCENTAGE / 100)
        );
        $activeStaff = $this->staffRepository->activeStaffCount((int) $instance->id);

        return max(0, $requiredTotalStaff - $activeStaff);
    }

    private function staffRequiredPerClub(): int
    {
        return SquadStaffConfig::MANAGER_COUNT
            + SquadStaffConfig::ASSISTANT_MANAGER_COUNT
            + SquadStaffConfig::FIRST_TEAM_COACH_COUNT
            + SquadStaffConfig::YOUTH_TEAM_COACH_COUNT
            + SquadStaffConfig::SCOUT_COUNT
            + SquadStaffConfig::PHYSIO_FIRST_TEAM_COUNT
            + SquadStaffConfig::PHYSIO_YOUTH_TEAM_COUNT;
    }
}
