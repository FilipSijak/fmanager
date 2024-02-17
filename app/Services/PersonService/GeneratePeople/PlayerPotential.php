<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Services\ClubService\SquadAnalysis\SquadPlayersConfig;
use App\Services\PersonService\PersonConfig\PersonTypes;

class PlayerPotential extends PersonPotential
{
    public function getPlayerPotentialAndInitialPosition(int $clubAcademyRank): array
    {
        $playerPotentialList = [];
        $rank                = $clubAcademyRank * 10;
        $positionsCount      = SquadPlayersConfig::POSITION_COUNT;

        for ($i = 1; $i <= SquadPlayersConfig::PLAYER_COUNT; $i++) {
            $newPlayer = new \stdClass();

            if ($i <= 5) {
                // special players
                $newPlayer->potential = rand($rank, 200);
            } elseif ($i > 5 && $i <= 15) {
                // normal players by club rank
                $newPlayer->potential = rand($rank - 15, $rank + 5);
            } else {
                // bellow average players
                $newPlayer->potential = rand($rank - 40, $rank - 20);
            }

            $playerPotentialList[] = $newPlayer;
        }

        shuffle($playerPotentialList);

        foreach ($playerPotentialList as $player) {
            foreach ($positionsCount as $position => $count) {
                if ($count == 0) {
                    continue;
                }

                $player->position = $position;
                $player->potentialByCategory = $this->calculatePotentialByCategory($player->potential);
                $positionsCount[$position]--;
                break;
            }
        }

        return $playerPotentialList;
    }

    public function getStaffPotentialAndRole(int $rank): array
    {
        $staffList  = [];
        $staffRoles = [
            PersonTypes::COACH             => 7,
            PersonTypes::YOUTH_COACH       => 5,
            PersonTypes::PHYSIO            => 3,
            PersonTypes::MANAGER           => 1,
            PersonTypes::ASSISTANT_MANAGER => 1,
        ];

        foreach ($staffRoles as $role => $count) {
            for ($i = 1; $i <= $count; $i++) {
                $newStaffMember = new \stdClass();

                $newStaffMember->role = $role;

                if ($role == PersonTypes::MANAGER) {
                    $newStaffMember->potential = rand($rank, 200);
                } elseif ($role == PersonTypes::ASSISTANT_MANAGER) {
                    $newStaffMember->potential = rand($rank - 20, $rank);
                } elseif ($role == PersonTypes::YOUTH_COACH) {
                    $newStaffMember->potential = rand($rank - 35, $rank - 20);
                } else {
                    $newStaffMember->potential = rand($rank - 10, $rank);
                }

                $staffList[] = $newStaffMember;
            }
        }

        return $staffList;
    }
}
