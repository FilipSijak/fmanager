<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Services\ClubService\SquadAnalysis\SquadPlayersConfig;
use App\Services\PersonService\PersonConfig\PersonTypes;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;

class PlayerPotential extends PersonPotential
{
    public function getPlayerPotential(int $rank): array
    {
        $playerPotentialList = [];
        $rank = $rank * 10;

        for ($i = 1; $i <= SquadPlayersConfig::PLAYER_COUNT; $i++) {
            $newPlayer = new \stdClass;

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

        return $playerPotentialList;
    }

    public function assignPlayerPositions(array $playersPotentialList): array
    {
        $positionsCount = SquadPlayersConfig::POSITION_COUNT;

        foreach ($playersPotentialList as $player) {
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

        return $playersPotentialList;
    }

    public function generateFreeAgent(int $maxPotential): \stdClass
    {
        $newPlayer = new \stdClass;

        $newPlayer->potential = rand(30, $maxPotential);
        $newPlayer->position = PlayerPositionConfig::PLAYER_POSITIONS[rand(1, 14)];
        $newPlayer->potentialByCategory = $this->calculatePotentialByCategory($newPlayer->potential);

        return $newPlayer;
    }

    public function getStaffPotentialAndRole(int $rank): array
    {
        $staffList = [];
        $staffRoles = [
            PersonTypes::COACH => 7,
            PersonTypes::YOUTH_COACH => 5,
            PersonTypes::PHYSIO => 3,
            PersonTypes::MANAGER => 1,
            PersonTypes::ASSISTANT_MANAGER => 1,
        ];

        foreach ($staffRoles as $role => $count) {
            for ($i = 1; $i <= $count; $i++) {
                $newStaffMember = new \stdClass;

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
