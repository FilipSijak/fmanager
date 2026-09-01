<?php

namespace App\Services\TrainingService;

use App\Models\Club;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;
use Illuminate\Support\Facades\DB;

class TrainingService
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

    public function executeTrainingSession(Club $club): int
    {
        $trainingFields = array_merge(
            PlayerFields::TECHNICAL_FIELDS,
            PlayerFields::MENTAL_FIELDS,
            PlayerFields::PHYSICAL_FIELDS
        );

        return DB::transaction(function () use ($club, $trainingFields): int {
            $trainingDate = DB::table('instances')
                ->where('id', $club->instance_id)
                ->value('instance_date');

            $players = DB::table('players')
                ->where('players.instance_id', $club->instance_id)
                ->where('players.club_id', $club->id)
                ->where('players.is_retired', false)
                ->select([
                    'players.id',
                    'players.potential',
                    'players.max_potential',
                    'players.physical',
                    'players.position',
                    ...$trainingFields,
                ])
                ->lockForUpdate()
                ->get();

            $progressByPlayer = DB::table('players_progress')
                ->whereIn('player_id', $players->pluck('id'))
                ->select(['player_id', 'condition', ...$trainingFields])
                ->lockForUpdate()
                ->get()
                ->keyBy('player_id');

            $schedulesByPlayer = DB::table('training_player_schedule')
                ->whereIn('player_id', $players->pluck('id'))
                ->select(['player_id', 'training_category_id', 'training_intensity_id'])
                ->lockForUpdate()
                ->get()
                ->groupBy('player_id')
                ->map(fn ($schedules) => $schedules->keyBy('training_category_id'));

            $injuredPlayerIds = DB::table('player_injuries')
                ->whereIn('player_id', $players->pluck('id'))
                ->whereDate('injury_start_date', '<=', $trainingDate)
                ->whereDate('injury_end_date', '>=', $trainingDate)
                ->pluck('player_id')
                ->mapWithKeys(fn ($playerId): array => [(int) $playerId => true]);

            $trainedPlayers = 0;

            foreach ($players as $player) {
                $progress = $progressByPlayer->get($player->id);

                if ($progress === null) {
                    continue;
                }

                if ($injuredPlayerIds->has($player->id)) {
                    $missedSessionUpdates = ['last_progressed_at' => now(), 'updated_at' => now()];

                    foreach ($trainingFields as $field) {
                        $missedSessionUpdates[$field] = max(
                            0,
                            (int) $progress->{$field} - self::MISSED_SESSION_PENALTY
                        );
                    }

                    DB::table('players_progress')
                        ->where('player_id', $player->id)
                        ->update($missedSessionUpdates);

                    continue;
                }

                $playerUpdates = [];
                $progressUpdates = ['last_progressed_at' => now(), 'updated_at' => now()];
                $gap = (int) $player->max_potential - (int) $player->potential;
                $atFullPotential = $gap <= 0;
                $hasProgressChange = false;
                $playerSchedules = $schedulesByPlayer->get($player->id);
                $conditionCost = $this->conditionCost($playerSchedules);

                if ($conditionCost > 0) {
                    $progressUpdates['condition'] = max(
                        self::MINIMUM_TRAINING_CONDITION,
                        (int) $progress->condition - $conditionCost
                    );
                } else {
                    $progressUpdates['condition'] = min(
                        100,
                        (int) $progress->condition + self::TRAINING_CONDITION_RECOVERY
                    );
                }
                $hasProgressChange = true;

                foreach ($this->fieldsByCategory() as $categoryId => $categoryFields) {
                    $schedule = $playerSchedules?->get($categoryId);

                    if ($schedule === null || $categoryFields === []) {
                        continue;
                    }

                    $points = $this->pointsForIntensity(
                        TrainingIntensity::from((int) $schedule->training_intensity_id),
                        $gap
                    );

                    if ($points === 0) {
                        continue;
                    }

                    $hasProgressChange = true;

                    foreach ($categoryFields as $field) {
                        $fieldPoints = $this->pointsForPositionPriority(
                            $categoryId,
                            $field,
                            $points,
                            $player->position
                        );
                        $totalProgress = max(0, (int) $progress->{$field} + $fieldPoints);
                        $progressThreshold = $this->progressThreshold($categoryId);
                        $requestedIncrease = intdiv($totalProgress, $progressThreshold);
                        $attributeIncrease = $this->allowedAttributeIncrease(
                            $categoryId,
                            $player,
                            $field,
                            $requestedIncrease,
                            $atFullPotential
                        );
                        $remainingProgress = $totalProgress
                            - ($attributeIncrease * $progressThreshold);
                        $progressUpdates[$field] = min(
                            $progressThreshold - 1,
                            $remainingProgress
                        );

                        if ($attributeIncrease > 0) {
                            $playerUpdates[$field] = (int) $player->{$field} + $attributeIncrease;
                        }
                    }
                }

                if (! $hasProgressChange) {
                    continue;
                }

                DB::table('players_progress')
                    ->where('player_id', $player->id)
                    ->update($progressUpdates);

                if ($playerUpdates !== []) {
                    DB::table('players')->where('id', $player->id)->update($playerUpdates);
                }

                $trainedPlayers++;
            }

            return $trainedPlayers;
        });
    }

    public function recoverCondition(Club $club): int
    {
        return DB::transaction(function () use ($club): int {
            $playerIds = DB::table('players')
                ->where('instance_id', $club->instance_id)
                ->where('club_id', $club->id)
                ->where('is_retired', false)
                ->lockForUpdate()
                ->pluck('id');

            $progressRows = DB::table('players_progress')
                ->whereIn('player_id', $playerIds)
                ->lockForUpdate()
                ->get(['player_id', 'condition']);
            $recoveredPlayers = 0;

            foreach ($progressRows as $progress) {
                $condition = min(
                    100,
                    (int) $progress->condition + self::REST_DAY_CONDITION_RECOVERY
                );

                if ($condition === (int) $progress->condition) {
                    continue;
                }

                DB::table('players_progress')
                    ->where('player_id', $progress->player_id)
                    ->update(['condition' => $condition, 'updated_at' => now()]);
                $recoveredPlayers++;
            }

            return $recoveredPlayers;
        });
    }

    private function pointsForPotentialGap(int $gap): int
    {
        if ($gap < self::MINIMUM_DEVELOPMENT_GAP) {
            return 0;
        }

        return min(self::MAX_POINTS_PER_SESSION, intdiv($gap, self::GAP_PER_POINT));
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

    private function conditionCost($playerSchedules): int
    {
        if ($playerSchedules === null) {
            return 0;
        }

        $onFieldCategoryIds = [
            TrainingCategory::Physical->value,
            TrainingCategory::Tactical->value,
            TrainingCategory::Technical->value,
        ];
        $effort = $playerSchedules
            ->whereIn('training_category_id', $onFieldCategoryIds)
            ->sum(fn ($schedule): int => $this->effortForIntensity(
                TrainingIntensity::from((int) $schedule->training_intensity_id)
            ));

        if ($effort > self::VERY_HARD_EFFORT_THRESHOLD) {
            return self::VERY_HARD_CONDITION_COST;
        }

        if ($effort > self::HARD_EFFORT_THRESHOLD) {
            return self::HARD_CONDITION_COST;
        }

        return 0;
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
        object $player,
        string $field,
        int $requestedIncrease,
        bool $atFullPotential
    ): int {
        if ($requestedIncrease === 0) {
            return 0;
        }

        $remainingToAbsoluteMaximum = max(
            0,
            self::MAX_ATTRIBUTE_VALUE - (int) $player->{$field}
        );
        $allowedIncrease = min($requestedIncrease, $remainingToAbsoluteMaximum);

        if (! $atFullPotential) {
            return $allowedIncrease;
        }

        if ($categoryId !== TrainingCategory::Physical->value) {
            return 0;
        }

        $physicalAttributeCeiling = (int) round((int) $player->physical / 10);
        $remainingGrowth = max(0, $physicalAttributeCeiling - (int) $player->{$field});

        return min($allowedIncrease, $remainingGrowth);
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

        if (in_array($field, $priorities['secondary'], true)) {
            return max(1, $points - 1);
        }

        return 1;
    }

    /**  array<int, array<int, string>> */
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
