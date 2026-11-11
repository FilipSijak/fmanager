<?php

namespace App\Services\ClubService\Domain;

use App\Services\PersonService\Data\GeneratedPlayerProfile;
use App\Services\PersonService\GeneratePeople\PlayerPotential;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;
use Random\Randomizer;

class YouthIntake
{
    public const int PLAYERS_PER_CLUB = 3;

    private readonly Randomizer $randomizer;

    public function __construct(
        private readonly PlayerPotential $playerPotential,
        ?Randomizer $randomizer = null,
    ) {
        $this->randomizer = $randomizer ?? new Randomizer;
    }

    /** @return list<GeneratedPlayerProfile> */
    public function profiles(int $academyRank): array
    {
        $academyPotential = max(1, min(20, $academyRank)) * 10;
        $minimumPotential = max(30, $academyPotential - 70);
        $maximumPotential = min(200, $academyPotential + 30);
        $positions = array_values(PlayerPositionConfig::PLAYER_POSITIONS);
        $profiles = [];

        for ($index = 0; $index < self::PLAYERS_PER_CLUB; $index++) {
            $potential = $this->randomizer->getInt($minimumPotential, $maximumPotential);
            $profiles[] = new GeneratedPlayerProfile(
                potential: $potential,
                position: $positions[$this->randomizer->getInt(0, count($positions) - 1)],
                potentialByCategory: $this->playerPotential->calculatePotentialByCategory($potential),
            );
        }

        return $profiles;
    }
}
