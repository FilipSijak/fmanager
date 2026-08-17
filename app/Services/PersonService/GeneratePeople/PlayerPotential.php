<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Services\ClubService\SquadAnalysis\SquadPlayersConfig;
use App\Services\PersonService\Data\GeneratedPlayerProfile;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;

class PlayerPotential extends PersonPotential
{
    /** @return list<GeneratedPlayerProfile> */
    public function createForClubRank(int $academyRank): array
    {
        $potentials = [];
        $rank = $academyRank * 10;

        for ($index = 1; $index <= SquadPlayersConfig::PLAYER_COUNT; $index++) {
            if ($index <= 5) {
                $potentials[] = $this->randomizer->getInt($rank, 200);
            } elseif ($index <= 15) {
                $potentials[] = $this->randomizer->getInt($rank - 15, $rank + 5);
            } else {
                $potentials[] = $this->randomizer->getInt($rank - 40, $rank - 20);
            }
        }

        $potentials = $this->randomizer->shuffleArray($potentials);
        $players = [];
        $potentialIndex = 0;

        foreach (SquadPlayersConfig::POSITION_COUNT as $position => $count) {
            for ($index = 0; $index < $count; $index++) {
                $potential = $potentials[$potentialIndex++];
                $players[] = new GeneratedPlayerProfile(
                    potential: $potential,
                    position: $position,
                    potentialByCategory: $this->calculatePotentialByCategory($potential),
                );
            }
        }

        return $players;
    }

    public function createFreeAgent(int $maxPotential): GeneratedPlayerProfile
    {
        $potential = $this->randomizer->getInt(30, $maxPotential);

        return new GeneratedPlayerProfile(
            potential: $potential,
            position: $this->randomizer->shuffleArray(array_values(PlayerPositionConfig::PLAYER_POSITIONS))[0],
            potentialByCategory: $this->calculatePotentialByCategory($potential),
        );
    }
}
