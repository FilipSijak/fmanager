<?php

namespace App\Services\TrainingService;

use App\Domain\PlayerDevelopment\TrainingCategory;

class PlayerFitnessCalculator
{
    public function conditionAfterMatch(int $condition, int $minutes, int $stamina, string $position, int $workrate = 10, int $teamwork = 10): int
    {
        $effort = 0.75 + (max(1, min(20, $workrate)) + max(1, min(20, $teamwork))) / 80;
        $cost = (34 - max(1, min(20, $stamina))) * $effort * max(0, $minutes) / 90;
        if ($position === 'GK') {
            $cost *= 0.5;
        }

        return max(0, min(100, $condition) - (int) round($cost));
    }

    public function performanceFactor(int $condition): float
    {
        return 0.5 + max(0, min(100, $condition)) / 200;
    }

    public function matchInjuryProbability(int $condition, int $minutes): float
    {
        return min(0.15, 0.012 * (1 + max(0, 100 - $condition) / 25) * max(0, $minutes) / 90);
    }

    /** @param array<int, Data\TrainingScheduleData> $schedules */
    public function trainingInjuryProbability(int $condition, string $position, array $schedules): float
    {
        $risk = 0.0;
        foreach ($schedules as $schedule) {
            if ($schedule->category === TrainingCategory::Goalkeeping && $position !== 'GK') {
                continue;
            }
            $risk += match ($schedule->intensity) {
                TrainingIntensity::None => 0.0,
                TrainingIntensity::Light => 0.0002,
                TrainingIntensity::Medium => 0.0005,
                TrainingIntensity::Hard => 0.0015,
            };
        }

        return min(0.05, $risk * (1 + max(0, 100 - $condition) / 25));
    }
}
