<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Services\ClubService\SquadAnalysis\SquadPlayersConfig;
use App\Services\PersonService\Data\PlayerPotentialData;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;

class PlayerPotential extends PersonPotential
{
    /** @return list<PlayerPotentialData> */
    public function createForClubRank(int $academyRank): array
    {
        $potentials = [];
        $rank = $academyRank * 10;

        for ($index = 1; $index <= SquadPlayersConfig::PLAYER_COUNT; $index++) {
            if ($index <= 5) {
                $potentials[] = rand($rank, 200);
            } elseif ($index <= 15) {
                $potentials[] = rand($rank - 15, $rank + 5);
            } else {
                $potentials[] = rand($rank - 40, $rank - 20);
            }
        }

        shuffle($potentials);
        $players = [];
        $potentialIndex = 0;

        foreach (SquadPlayersConfig::POSITION_COUNT as $position => $count) {
            for ($index = 0; $index < $count; $index++) {
                $potential = $potentials[$potentialIndex++];
                $players[] = new PlayerPotentialData(
                    potential: $potential,
                    position: $position,
                    potentialByCategory: $this->calculatePotentialByCategory($potential),
                );
            }
        }

        return $players;
    }

    public function createFreeAgent(int $maxPotential): PlayerPotentialData
    {
        $potential = rand(30, $maxPotential);

        return new PlayerPotentialData(
            potential: $potential,
            position: PlayerPositionConfig::PLAYER_POSITIONS[array_rand(PlayerPositionConfig::PLAYER_POSITIONS)],
            potentialByCategory: $this->calculatePotentialByCategory($potential),
        );
    }
}
