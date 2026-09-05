<?php

namespace Tests\Unit\Services\TrainingService;

use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use App\Services\TrainingService\Data\TrainingPlayerData;
use App\Services\TrainingService\Data\TrainingScheduleData;
use App\Services\TrainingService\PlayerProgressCalculator;
use App\Services\TrainingService\TrainingCategory;
use App\Services\TrainingService\TrainingIntensity;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerProgressCalculatorTest extends TestCase
{
    #[Test]
    public function an_injured_player_loses_accumulated_progress_without_player_updates(): void
    {
        $timestamp = CarbonImmutable::parse('2027-06-10');
        $player = new TrainingPlayerData(1, 100, 150, 100, 100, 100, 'CB', true, 95, ['pace' => 1], ['pace' => 1]);

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
        $player = new TrainingPlayerData(1, 100, 150, 100, 100, 100, 'CB', false, 95, [], []);

        $updates = (new PlayerProgressCalculator)->forTrainingSession($player, [], [], $timestamp);

        $this->assertSame(98, $updates->progress['condition']);
    }

    #[Test]
    public function a_player_below_the_minimum_condition_misses_training(): void
    {
        $timestamp = CarbonImmutable::parse('2027-06-10');
        $player = new TrainingPlayerData(1, 100, 150, 100, 100, 100, 'CB', false, 69, ['pace' => 12], ['pace' => 50]);

        $updates = (new PlayerProgressCalculator)->forTrainingSession(
            $player,
            [],
            ['pace'],
            $timestamp
        );

        $this->assertSame(48, $updates->progress['pace']);
        $this->assertArrayNotHasKey('condition', $updates->progress);
        $this->assertSame([], $updates->player);
    }

    #[Test]
    public function category_strengths_produce_different_development_gaps(): void
    {
        $timestamp = CarbonImmutable::parse('2027-06-10');
        $fields = [...PlayerFields::TECHNICAL_FIELDS, ...PlayerFields::PHYSICAL_FIELDS];
        $player = new TrainingPlayerData(
            1,
            100,
            150,
            60,
            120,
            180,
            'CB',
            false,
            100,
            array_fill_keys($fields, 10),
            array_fill_keys($fields, 0),
        );
        $schedules = [
            TrainingCategory::Technical->value => new TrainingScheduleData(
                TrainingCategory::Technical,
                TrainingIntensity::Medium,
            ),
            TrainingCategory::Physical->value => new TrainingScheduleData(
                TrainingCategory::Physical,
                TrainingIntensity::Medium,
            ),
        ];

        $updates = (new PlayerProgressCalculator)->forTrainingSession(
            $player,
            $schedules,
            $fields,
            $timestamp
        );

        $this->assertSame(2, $updates->progress['marking']);
        $this->assertSame(3, $updates->progress['strength']);
    }

    #[Test]
    public function rest_recovery_is_capped_at_full_condition(): void
    {
        $this->assertSame(100, (new PlayerProgressCalculator)->recoveredCondition(95));
    }
}
