<?php

namespace App\Services\TrainingService;

use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;
use App\Services\TrainingService\Data\TrainingPlayerData;
use Carbon\CarbonInterface;

class PlayerProgressCalculator
{
    private const MINIMUM_DEVELOPMENT_GAP = 10;

    private const GAP_PER_POINT = 10;

    private const MAX_POINTS_PER_SESSION = 3;

    private const MAX_HARD_POINTS_PER_SESSION = 5;

    private const PROGRESS_THRESHOLD = 100;

    private const TACTICAL_PROGRESS_THRESHOLD = 200;

    private const MAX_ATTRIBUTE_VALUE = 20;

    private const MISSED_SESSION_PENALTY = 2;

    private const HARD_EFFORT_THRESHOLD = 15;

    private const VERY_HARD_EFFORT_THRESHOLD = 18;

    private const HARD_CONDITION_COST = 3;

    private const VERY_HARD_CONDITION_COST = 5;

    private const MINIMUM_TRAINING_CONDITION = 70;

    private const TRAINING_CONDITION_RECOVERY = 3;

    private const REST_DAY_CONDITION_RECOVERY = 10;

    public function forTrainingSession(
        TrainingPlayerData $player,
        array $schedules,
        array $trainingFields,
        CarbonInterface $timestamp
    ): PlayerProgressUpdate {
        if ($player->injured || $player->condition < self::MINIMUM_TRAINING_CONDITION) {
            return new PlayerProgressUpdate(
                progress: $this->missedSessionProgress($player, $trainingFields, $timestamp),
                player: [],
            );
        }

        $playerUpdates = [];
        $progressUpdates = ['last_progressed_at' => $timestamp, 'updated_at' => $timestamp];
        $atFullPotential = $player->potential >= $player->maxPotential;
        $conditionCost = $this->conditionCost($schedules);
        $progressUpdates['condition'] = $conditionCost > 0
            ? max(self::MINIMUM_TRAINING_CONDITION, (int) $player->condition - $conditionCost)
            : min(100, (int) $player->condition + self::TRAINING_CONDITION_RECOVERY);

        foreach ($this->fieldsByCategory() as $categoryId => $categoryFields) {
            $schedule = $schedules[$categoryId] ?? null;

            if ($schedule === null || $categoryFields === []) {
                continue;
            }

            $points = $this->pointsForIntensity(
                $schedule->intensity,
                $this->categoryPotentialGap($categoryId, $player)
            );

            if ($points === 0) {
                continue;
            }

            foreach ($categoryFields as $field) {
                $fieldPoints = $this->pointsForPositionPriority(
                    $categoryId,
                    $field,
                    $points,
                    $player->position
                );
                $totalProgress = max(0, $player->accumulatedProgress($field) + $fieldPoints);
                $progressThreshold = $this->progressThreshold($categoryId);
                $requestedIncrease = intdiv($totalProgress, $progressThreshold);
                $attributeIncrease = $this->allowedAttributeIncrease(
                    $categoryId,
                    $player,
                    $field,
                    $requestedIncrease,
                    $atFullPotential
                );
                $remainingProgress = $totalProgress - ($attributeIncrease * $progressThreshold);
                $progressUpdates[$field] = min($progressThreshold - 1, $remainingProgress);

                if ($attributeIncrease > 0) {
                    $playerUpdates[$field] = $player->attribute($field) + $attributeIncrease;
                }
            }
        }

        return new PlayerProgressUpdate($progressUpdates, $playerUpdates);
    }

    public function recoveredCondition(int $condition): int
    {
        return min(100, $condition + self::REST_DAY_CONDITION_RECOVERY);
    }

    private function missedSessionProgress(
        TrainingPlayerData $player,
        array $trainingFields,
        CarbonInterface $timestamp
    ): array {
        $updates = ['last_progressed_at' => $timestamp, 'updated_at' => $timestamp];

        foreach ($trainingFields as $field) {
            $updates[$field] = max(0, $player->accumulatedProgress($field) - self::MISSED_SESSION_PENALTY);
        }

        return $updates;
    }

    private function pointsForPotentialGap(int $gap): int
    {
        return $gap < self::MINIMUM_DEVELOPMENT_GAP
            ? 0
            : min(self::MAX_POINTS_PER_SESSION, intdiv($gap, self::GAP_PER_POINT));
    }

    private function categoryPotentialGap(int $categoryId, TrainingPlayerData $player): int
    {
        if ($player->maxPotential <= 0) {
            return 0;
        }

        $categoryMaximum = match ($categoryId) {
            TrainingCategory::Technical->value => $player->technical,
            TrainingCategory::Tactical->value => $player->mental,
            TrainingCategory::Physical->value => $player->physical,
            default => 0,
        };
        $developmentRatio = min(1, max(0, $player->potential / $player->maxPotential));
        $currentCategoryPotential = (int) round($categoryMaximum * $developmentRatio);

        return max(0, $categoryMaximum - $currentCategoryPotential);
    }

    private function pointsForIntensity(TrainingIntensity $intensity, int $gap): int
    {
        return match ($intensity) {
            TrainingIntensity::Light => 0,
            TrainingIntensity::Medium => $this->pointsForPotentialGap($gap),
            TrainingIntensity::Hard => min(
                self::MAX_HARD_POINTS_PER_SESSION,
                $this->pointsForPotentialGap($gap) + 2
            ),
            TrainingIntensity::None => -self::MISSED_SESSION_PENALTY,
        };
    }

    private function conditionCost(array $schedules): int
    {
        if ($schedules === []) {
            return 0;
        }

        $effort = collect($schedules)
            ->whereIn('category.value', [
                TrainingCategory::Physical->value,
                TrainingCategory::Tactical->value,
                TrainingCategory::Technical->value,
            ])
            ->sum(fn ($schedule): int => $this->effortForIntensity(
                $schedule->intensity
            ));

        if ($effort > self::VERY_HARD_EFFORT_THRESHOLD) {
            return self::VERY_HARD_CONDITION_COST;
        }

        return $effort > self::HARD_EFFORT_THRESHOLD ? self::HARD_CONDITION_COST : 0;
    }

    private function effortForIntensity(TrainingIntensity $intensity): int
    {
        return match ($intensity) {
            TrainingIntensity::None => 0,
            TrainingIntensity::Light => 3,
            TrainingIntensity::Medium => 5,
            TrainingIntensity::Hard => 8,
        };
    }

    private function progressThreshold(int $categoryId): int
    {
        return $categoryId === TrainingCategory::Tactical->value
            ? self::TACTICAL_PROGRESS_THRESHOLD
            : self::PROGRESS_THRESHOLD;
    }

    private function allowedAttributeIncrease(
        int $categoryId,
        TrainingPlayerData $player,
        string $field,
        int $requestedIncrease,
        bool $atFullPotential
    ): int {
        if ($requestedIncrease === 0) {
            return 0;
        }

        $allowedIncrease = min(
            $requestedIncrease,
            max(0, self::MAX_ATTRIBUTE_VALUE - $player->attribute($field))
        );

        if (! $atFullPotential) {
            return $allowedIncrease;
        }

        if ($categoryId !== TrainingCategory::Physical->value) {
            return 0;
        }

        $physicalAttributeCeiling = (int) round((int) $player->physical / 10);

        return min(
            $allowedIncrease,
            max(0, $physicalAttributeCeiling - $player->attribute($field))
        );
    }

    private function pointsForPositionPriority(
        int $categoryId,
        string $field,
        int $points,
        string $position
    ): int {
        if ($points <= 0) {
            return $points;
        }

        $positionCategory = match ($categoryId) {
            TrainingCategory::Physical->value => 'physical',
            TrainingCategory::Tactical->value => 'mental',
            TrainingCategory::Technical->value => 'technical',
            default => null,
        };

        if ($positionCategory === null) {
            return 0;
        }

        $priorities = PlayerPositionConfig::getPositionMainAttributes($position)[$positionCategory];

        if (in_array($field, $priorities['primary'], true)) {
            return $points;
        }

        return in_array($field, $priorities['secondary'], true) ? max(1, $points - 1) : 1;
    }

    private function fieldsByCategory(): array
    {
        return [
            TrainingCategory::Physical->value => PlayerFields::PHYSICAL_FIELDS,
            TrainingCategory::Tactical->value => PlayerFields::MENTAL_FIELDS,
            TrainingCategory::Technical->value => PlayerFields::TECHNICAL_FIELDS,
            TrainingCategory::Goalkeeping->value => [],
        ];
    }
}
