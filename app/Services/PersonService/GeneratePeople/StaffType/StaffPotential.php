<?php

namespace App\Services\PersonService\GeneratePeople\StaffType;

use App\Services\ClubService\SquadAnalysis\SquadStaffConfig;
use App\Services\PersonService\GeneratePeople\PersonPotential;
use App\Services\PersonService\PersonConfig\PersonTypes;

class StaffPotential extends PersonPotential
{
    private const MINIMUM_RANK = 1;

    private const MAXIMUM_RANK = 20;

    /** @return list<StaffPotentialData> */
    public function getStaffPotentialAndRole(int $rank): array
    {
        $staffList = [];
        foreach ($this->staffRoles() as $role => $count) {
            for ($i = 1; $i <= $count; $i++) {
                $staffList[] = $this->createStaffPotential($role, $rank);
            }
        }

        return $staffList;
    }

    public function getRandomStaffPotential(): StaffPotentialData
    {
        $roles = [];

        foreach ($this->staffRoles() as $role => $count) {
            $roles = array_merge($roles, array_fill(0, $count, $role));
        }

        return $this->createStaffPotential(
            $roles[array_rand($roles)],
            random_int(self::MINIMUM_RANK, self::MAXIMUM_RANK)
        );
    }

    public function getRandomPotentialForRole(string $role): StaffPotentialData
    {
        return $this->createStaffPotential(
            $role,
            random_int(self::MINIMUM_RANK, self::MAXIMUM_RANK)
        );
    }

    public function getRandomFormerPlayerStaffPotential(): StaffPotentialData
    {
        $roles = [
            PersonTypes::MANAGER,
            PersonTypes::ASSISTANT_MANAGER,
            PersonTypes::COACH,
        ];

        return $this->createStaffPotential(
            $roles[array_rand($roles)],
            random_int(self::MINIMUM_RANK, self::MAXIMUM_RANK)
        );
    }

    /** @return array<string, int> */
    private function staffRoles(): array
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

    public function createStaffPotential(string $role, int $rank): StaffPotentialData
    {
        $rank *= 10;

        if ($role === PersonTypes::MANAGER) {
            $minimumPotential = $rank;
            $maximumPotential = min(200, $rank + 20);
        } elseif ($role === PersonTypes::ASSISTANT_MANAGER) {
            $minimumPotential = $rank - 20;
            $maximumPotential = $rank;
        } elseif ($role === PersonTypes::YOUTH_COACH) {
            $minimumPotential = $rank - 35;
            $maximumPotential = $rank - 10;
        } else {
            $minimumPotential = $rank - 15;
            $maximumPotential = $rank + 5;
        }

        return new StaffPotentialData(
            role: $role,
            potential: rand(max(30, $minimumPotential), min(200, max(30, $maximumPotential))),
            rank: (int) ($rank / 10),
        );
    }
}
