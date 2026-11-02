<?php

namespace App\Services\TrainingService;

use App\Domain\PlayerDevelopment\TrainingCategory;
use App\Services\TrainingService\Data\TrainingScheduleData;

final readonly class TrainingPlan
{
    /** @param array{physical: int, tactical: int, technical: int, goalkeeping: int} $schedule */
    public function __construct(public array $schedule) {}

    /** @param array<int, TrainingScheduleData> $schedules */
    public static function fromSchedules(array $schedules): self
    {
        $plan = [];

        foreach (TrainingCategory::cases() as $category) {
            $plan[strtolower($category->name)] = ($schedules[$category->value] ?? null)?->intensity->value ?? TrainingIntensity::Medium->value;
        }

        return new self($plan);
    }

    /** @return array<int, TrainingScheduleData> */
    public function schedules(): array
    {
        $schedules = [];

        foreach (TrainingCategory::cases() as $category) {
            $schedules[$category->value] = new TrainingScheduleData($category, TrainingIntensity::from((int) $this->schedule[strtolower($category->name)]));
        }

        return $schedules;
    }

    /**
     * @param  list<int>  $playerIds
     * @return list<array{player_id: int, training_category_id: int, training_intensity_id: int}>
     */
    public function rowsForPlayers(array $playerIds): array
    {
        $rows = [];

        foreach ($playerIds as $playerId) {
            foreach ($this->schedules() as $schedule) {
                $rows[] = ['player_id' => $playerId, 'training_category_id' => $schedule->category->value, 'training_intensity_id' => $schedule->intensity->value];
            }
        }

        return $rows;
    }

    /** @return list<array{id: string, name: string, goalkeeper_only: bool, schedule: array<string, int>}> */
    public static function presets(): array
    {
        $presets = [];

        foreach (TrainingIntensity::cases() as $intensity) {
            $presets[] = [
                'id' => strtolower($intensity->name),
                'name' => $intensity === TrainingIntensity::None ? 'Full rest' : 'All '.$intensity->name,
                'goalkeeper_only' => false,
                'schedule' => collect(TrainingCategory::cases())->mapWithKeys(fn (TrainingCategory $category): array => [strtolower($category->name) => $intensity->value])->all(),
            ];
        }

        foreach (TrainingCategory::cases() as $focus) {
            $presets[] = [
                'id' => 'focus_'.strtolower($focus->name), 'name' => $focus->name.' focus',
                'goalkeeper_only' => $focus === TrainingCategory::Goalkeeping,
                'schedule' => collect(TrainingCategory::cases())->mapWithKeys(fn (TrainingCategory $category): array => [strtolower($category->name) => ($category === $focus ? TrainingIntensity::Hard : TrainingIntensity::Light)->value])->all(),
            ];
        }

        return $presets;
    }
}
