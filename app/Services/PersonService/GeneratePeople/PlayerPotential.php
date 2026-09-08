<?php

namespace App\Services\PersonService\GeneratePeople;

use App\Domain\PlayerDevelopment\AgePotentialCurve;
use App\Services\ClubService\SquadAnalysis\SquadPlayersConfig;
use App\Services\PersonService\Data\GeneratedPlayerProfile;
use App\Services\PersonService\Data\PotentialByCategoryData;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;
use Carbon\CarbonInterface;

class PlayerPotential extends PersonPotential
{
    private readonly AgePotentialCurve $agePotentialCurve;

    private readonly AgePotentialCurve $technicalAgePotentialCurve;

    private readonly AgePotentialCurve $mentalAgePotentialCurve;

    private readonly AgePotentialCurve $physicalAgePotentialCurve;

    public function __construct()
    {
        $this->agePotentialCurve = new AgePotentialCurve(self::AGE_POTENTIAL_BRACKETS);
        $this->technicalAgePotentialCurve = new AgePotentialCurve(self::TECHNICAL_AGE_POTENTIAL_BRACKETS);
        $this->mentalAgePotentialCurve = new AgePotentialCurve(self::MENTAL_AGE_POTENTIAL_BRACKETS);
        $this->physicalAgePotentialCurve = new AgePotentialCurve(self::PHYSICAL_AGE_POTENTIAL_BRACKETS);
    }

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

    private function forAge(int $maxPotential, int $age): float
    {
        return $this->agePotentialCurve->potentialFor($maxPotential, $age);
    }

    private const TECHNICAL_AGE_POTENTIAL_BRACKETS = [
        16 => 0.85,
        18 => 0.90,
        21 => 0.95,
        24 => 1.00,
        29 => 0.99,
        30 => 0.98,
        32 => 0.97,
        33 => 0.95,
        35 => 0.93,
        38 => 0.90,
        41 => 0.85,
    ];

    private const MENTAL_AGE_POTENTIAL_BRACKETS = [
        16 => 0.85,
        18 => 0.90,
        21 => 0.95,
        24 => 1.00,
        29 => 0.99,
        30 => 0.98,
        32 => 0.96,
        33 => 0.94,
        35 => 0.91,
        38 => 0.88,
        41 => 0.82,
    ];

    private const PHYSICAL_AGE_POTENTIAL_BRACKETS = [
        16 => 0.85,
        18 => 0.90,
        21 => 0.95,
        24 => 1.00,
        29 => 0.98,
        30 => 0.95,
        31 => 0.92,
        32 => 0.88,
        33 => 0.84,
        34 => 0.80,
        35 => 0.75,
        36 => 0.71,
        37 => 0.67,
        38 => 0.62,
        39 => 0.58,
        40 => 0.54,
        41 => 0.50,
    ];

    public function potentialByCategoryOnDate(
        PotentialByCategoryData $maxPotential,
        CarbonInterface $dateOfBirth,
        CarbonInterface $asOfDate
    ): PotentialByCategoryData {
        $age = (int) $dateOfBirth->diffInYears($asOfDate);

        return new PotentialByCategoryData(
            technical: (int) round($this->technicalAgePotentialCurve->potentialFor($maxPotential->technical, $age)),
            mental: (int) round($this->mentalAgePotentialCurve->potentialFor($maxPotential->mental, $age)),
            physical: (int) round($this->physicalAgePotentialCurve->potentialFor($maxPotential->physical, $age)),
        );
    }

    public function onDate(
        int $maxPotential,
        CarbonInterface $dateOfBirth,
        CarbonInterface $asOfDate
    ): float {
        return $this->forAge($maxPotential, (int) $dateOfBirth->diffInYears($asOfDate));
    }
}
