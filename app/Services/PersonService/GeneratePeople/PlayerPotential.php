<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Services\ClubService\SquadAnalysis\SquadPlayersConfig;
use App\Services\PersonService\Data\GeneratedPlayerProfile;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;
use Carbon\CarbonInterface;

class PlayerPotential extends PersonPotential
{
    private const AGE_POTENTIAL_BRACKETS = [
        16 => 0.85,
        18 => 0.90,
        21 => 0.95,
        24 => 1.00,
        29 => 0.98,
        30 => 0.95,
        32 => 0.92,
        33 => 0.89,
        35 => 0.83,
        38 => 0.75,
        41 => 0.67,
    ];

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

    public function forAge(int $maxPotential, int $age): float
    {
        $multiplier = self::AGE_POTENTIAL_BRACKETS[16];

        foreach (self::AGE_POTENTIAL_BRACKETS as $minimumAge => $ageMultiplier) {
            if ($age < $minimumAge) {
                break;
            }

            $multiplier = $ageMultiplier;
        }

        return $maxPotential * $multiplier;
    }

    public function onDate(
        int $maxPotential,
        CarbonInterface $dateOfBirth,
        CarbonInterface $asOfDate
    ): float {
        return $this->forAge($maxPotential, $dateOfBirth->diffInYears($asOfDate));
    }
}
