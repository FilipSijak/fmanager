<?php

namespace Tests\Unit\Services\TrainingService;

use App\Services\TrainingService\Data\TrainingPlayerData;
use App\Services\TrainingService\PlayerProgressCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerProgressCalculatorTest extends TestCase
{
    #[Test]
    public function an_injured_player_loses_accumulated_progress_without_player_updates(): void
    {
        $timestamp = CarbonImmutable::parse('2027-06-10');
        $player = new TrainingPlayerData(1, 100, 150, 100, 'CB', true, 95, ['pace' => 1], ['pace' => 1]);

        $updates = (new PlayerProgressCalculator)->forTrainingSession(
            $player,
            [],
            ['pace'],
            $timestamp
        );

        $this->assertSame(0, $updates->progress['pace']);
        $this->assertSame([], $updates->player);
        $this->assertSame($timestamp, $updates->progress['updated_at']);
    }

    #[Test]
    public function a_player_without_scheduled_training_recovers_condition(): void
    {
        $timestamp = CarbonImmutable::parse('2027-06-10');
        $player = new TrainingPlayerData(1, 100, 150, 100, 'CB', false, 95, [], []);

        $updates = (new PlayerProgressCalculator)->forTrainingSession($player, [], [], $timestamp);

        $this->assertSame(98, $updates->progress['condition']);
    }

    #[Test]
    public function rest_recovery_is_capped_at_full_condition(): void
    {
        $this->assertSame(100, (new PlayerProgressCalculator)->recoveredCondition(95));
    }
}
