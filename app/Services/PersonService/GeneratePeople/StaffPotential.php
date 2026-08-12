<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Services\ClubService\SquadAnalysis\SquadStaffConfig;
use App\Services\PersonService\PersonConfig\PersonTypes;

class StaffPotential extends PersonPotential
{
    public function getStaffPotentialAndRole(int $rank): array
    {
        $staffList = [];
        $rank *= 10;
        $staffRoles = [
            PersonTypes::MANAGER => SquadStaffConfig::MANAGER_COUNT,
            PersonTypes::ASSISTANT_MANAGER => SquadStaffConfig::ASSISTANT_MANAGER_COUNT,
            PersonTypes::COACH => SquadStaffConfig::FIRST_TEAM_COACH_COUNT,
            PersonTypes::YOUTH_COACH => SquadStaffConfig::YOUTH_TEAM_COACH_COUNT,
            PersonTypes::SCOUT => SquadStaffConfig::SCOUT_COUNT,
            PersonTypes::PHYSIO => SquadStaffConfig::PHYSIO_FIRST_TEAM_COUNT,
            PersonTypes::YOUTH_PHYSIO => SquadStaffConfig::PHYSIO_YOUTH_TEAM_COUNT,
        ];

        foreach ($staffRoles as $role => $count) {
            for ($i = 1; $i <= $count; $i++) {
                $newStaffMember = new \stdClass;

                $newStaffMember->role = $role;

                if ($role == PersonTypes::MANAGER) {
                    $minimumPotential = $rank;
                    $maximumPotential = min(200, $rank + 20);
                } elseif ($role == PersonTypes::ASSISTANT_MANAGER) {
                    $minimumPotential = $rank - 20;
                    $maximumPotential = $rank;
                } elseif ($role == PersonTypes::YOUTH_COACH) {
                    $minimumPotential = $rank - 35;
                    $maximumPotential = $rank - 10;
                } else {
                    $minimumPotential = $rank - 15;
                    $maximumPotential = $rank + 5;
                }

                $newStaffMember->potential = rand(max(30, $minimumPotential), min(200, max(30, $maximumPotential)));
                $staffList[] = $newStaffMember;
            }
        }

        return $staffList;
    }
}
