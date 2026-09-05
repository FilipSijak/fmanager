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
    public function hard_training_respects_primary_secondary_and_other_priorities(): void
    {
        $fields = PlayerFields::TECHNICAL_FIELDS;
        $updates = (new PlayerProgressCalculator)->forTrainingSession(
            $this->player(
                technical: 180,
                attributes: array_fill_keys($fields, 10),
                progress: array_fill_keys($fields, 0),
            ),
            $this->schedules([TrainingCategory::Technical->value => TrainingIntensity::Hard]),
            $fields,
            CarbonImmutable::parse('2027-06-10'),
        );

        $this->assertSame(5, $updates->progress['marking']);
        $this->assertSame(4, $updates->progress['heading']);
        $this->assertSame(1, $updates->progress['finishing']);
    }

    #[Test]
    public function combined_hard_schedules_apply_condition_costs(): void
    {
        $fields = [...PlayerFields::TECHNICAL_FIELDS, ...PlayerFields::MENTAL_FIELDS, ...PlayerFields::PHYSICAL_FIELDS];
        $player = $this->player(
            condition: 90,
            attributes: array_fill_keys($fields, 10),
            progress: array_fill_keys($fields, 0),
        );
        $calculator = new PlayerProgressCalculator;

        $hard = $this->schedules([
            TrainingCategory::Technical->value => TrainingIntensity::Hard,
            TrainingCategory::Tactical->value => TrainingIntensity::Hard,
        ]);
        $veryHard = $hard + [
            TrainingCategory::Physical->value => new TrainingScheduleData(
                TrainingCategory::Physical,
                TrainingIntensity::Hard,
            ),
        ];

        $hardUpdates = $calculator->forTrainingSession($player, $hard, $fields, CarbonImmutable::parse('2027-06-10'));
        $veryHardUpdates = $calculator->forTrainingSession($player, $veryHard, $fields, CarbonImmutable::parse('2027-06-10'));

        $this->assertSame(87, $hardUpdates->progress['condition']);
        $this->assertSame(85, $veryHardUpdates->progress['condition']);
    }

    #[Test]
    public function tactical_training_requires_two_hundred_progress_for_an_attribute(): void
    {
        $fields = PlayerFields::MENTAL_FIELDS;
        $progress = array_fill_keys($fields, 0);
        $progress['positioning'] = 197;
        $updates = (new PlayerProgressCalculator)->forTrainingSession(
            $this->player(
                mental: 180,
                attributes: array_fill_keys($fields, 10),
                progress: $progress,
            ),
            $this->schedules([TrainingCategory::Tactical->value => TrainingIntensity::Medium]),
            $fields,
            CarbonImmutable::parse('2027-06-10'),
        );

        $this->assertSame(11, $updates->player['positioning']);
        $this->assertSame(0, $updates->progress['positioning']);
    }

    #[Test]
    public function full_potential_blocks_technical_growth_but_allows_physical_restoration(): void
    {
        $fields = [...PlayerFields::TECHNICAL_FIELDS, ...PlayerFields::PHYSICAL_FIELDS];
        $attributes = array_fill_keys($fields, 10);
        $attributes['marking'] = 14;
        $attributes['strength'] = 14;
        $progress = array_fill_keys($fields, 0);
        $progress['marking'] = 99;
        $progress['strength'] = 99;
        $updates = (new PlayerProgressCalculator)->forTrainingSession(
            $this->player(
                potential: 150,
                maxPotential: 150,
                technical: 150,
                physical: 150,
                attributes: $attributes,
                progress: $progress,
            ),
            $this->schedules([
                TrainingCategory::Technical->value => TrainingIntensity::Hard,
                TrainingCategory::Physical->value => TrainingIntensity::Hard,
            ]),
            $fields,
            CarbonImmutable::parse('2027-06-10'),
        );

        $this->assertArrayNotHasKey('marking', $updates->player);
        $this->assertSame(99, $updates->progress['marking']);
        $this->assertSame(15, $updates->player['strength']);
        $this->assertSame(1, $updates->progress['strength']);
    }

    #[Test]
    public function development_is_capped_by_current_category_and_position_importance(): void
    {
        $fields = PlayerFields::TECHNICAL_FIELDS;
        $attributes = array_fill_keys($fields, 10);
        $attributes['marking'] = 9;
        $attributes['heading'] = 8;
        $attributes['finishing'] = 7;
        $progress = array_fill_keys($fields, 0);
        $progress['marking'] = 199;
        $progress['heading'] = 199;
        $progress['finishing'] = 199;
        $updates = (new PlayerProgressCalculator)->forTrainingSession(
            $this->player(
                potential: 100,
                maxPotential: 150,
                technical: 150,
                attributes: $attributes,
                progress: $progress,
            ),
            $this->schedules([TrainingCategory::Technical->value => TrainingIntensity::Hard]),
            $fields,
            CarbonImmutable::parse('2027-06-10'),
        );

        $this->assertSame(10, $updates->player['marking']);
        $this->assertSame(9, $updates->player['heading']);
        $this->assertSame(8, $updates->player['finishing']);
        $this->assertSame(99, $updates->progress['marking']);
        $this->assertSame(99, $updates->progress['heading']);
        $this->assertSame(99, $updates->progress['finishing']);
    }

    #[Test]
    public function none_intensity_reduces_progress_and_recovers_condition(): void
    {
        $fields = PlayerFields::TECHNICAL_FIELDS;
        $updates = (new PlayerProgressCalculator)->forTrainingSession(
            $this->player(
                condition: 90,
                attributes: array_fill_keys($fields, 10),
                progress: array_fill_keys($fields, 5),
            ),
            $this->schedules([TrainingCategory::Technical->value => TrainingIntensity::None]),
            $fields,
            CarbonImmutable::parse('2027-06-10'),
        );

        $this->assertSame(3, $updates->progress['marking']);
        $this->assertSame(93, $updates->progress['condition']);
    }

    private function player(
        int $potential = 100,
        int $maxPotential = 150,
        int $technical = 150,
        int $mental = 150,
        int $physical = 150,
        int $condition = 100,
        array $attributes = [],
        array $progress = [],
    ): TrainingPlayerData {
        return new TrainingPlayerData(
            id: 1,
            potential: $potential,
            maxPotential: $maxPotential,
            technical: $technical,
            mental: $mental,
            physical: $physical,
            position: 'CB',
            injured: false,
            condition: $condition,
            attributes: $attributes,
            progress: $progress,
        );
    }

    private function schedules(array $intensities): array
    {
        $schedules = [];

        foreach ($intensities as $categoryId => $intensity) {
            $category = TrainingCategory::from($categoryId);
            $schedules[$categoryId] = new TrainingScheduleData($category, $intensity);
        }

        return $schedules;
    }
}
