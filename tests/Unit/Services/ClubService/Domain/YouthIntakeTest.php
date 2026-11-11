<?php

namespace Tests\Unit\Services\ClubService\Domain;

use App\Services\ClubService\Domain\YouthIntake;
use App\Services\PersonService\GeneratePeople\PlayerPotential;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

class YouthIntakeTest extends TestCase
{
    public function test_academy_rank_influences_intake_potential_within_valid_limits(): void
    {
        $randomizer = new Randomizer(new Mt19937(123));
        $intake = new YouthIntake(new PlayerPotential($randomizer), $randomizer);

        $lowAcademy = $intake->profiles(1);
        $highAcademy = $intake->profiles(20);

        $this->assertCount(3, $lowAcademy);
        $this->assertCount(3, $highAcademy);
        foreach ($lowAcademy as $profile) {
            $this->assertGreaterThanOrEqual(30, $profile->potential);
            $this->assertLessThanOrEqual(40, $profile->potential);
            $this->assertContains($profile->position, PlayerPositionConfig::PLAYER_POSITIONS);
        }
        foreach ($highAcademy as $profile) {
            $this->assertGreaterThanOrEqual(130, $profile->potential);
            $this->assertLessThanOrEqual(200, $profile->potential);
            foreach ((array) $profile->potentialByCategory as $potential) {
                $this->assertGreaterThanOrEqual(0, $potential);
                $this->assertLessThanOrEqual(200, $potential);
            }
        }
    }

    public function test_out_of_range_academy_ranks_are_clamped(): void
    {
        $randomizer = new Randomizer(new Mt19937(123));
        $intake = new YouthIntake(new PlayerPotential($randomizer), $randomizer);

        $lowAcademy = $intake->profiles(-1);
        $highAcademy = $intake->profiles(200);

        foreach ($lowAcademy as $profile) {
            $this->assertGreaterThanOrEqual(30, $profile->potential);
            $this->assertLessThanOrEqual(40, $profile->potential);
        }
        foreach ($highAcademy as $profile) {
            $this->assertGreaterThanOrEqual(130, $profile->potential);
            $this->assertLessThanOrEqual(200, $profile->potential);
        }
    }
}
