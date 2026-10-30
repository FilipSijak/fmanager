<?php

namespace App\Services\TrainingService;

use App\Domain\PlayerDevelopment\PlayerDevelopmentCategory;
use App\Domain\PlayerDevelopment\TrainingCategory;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use App\Services\TrainingService\Data\TrainingScheduleData;
use App\Support\GameContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TrainingManagementService
{
    public function __construct(
        private readonly GameContext $context,
        private readonly PlayerProgressCalculator $calculator,
    ) {}

    public function managedClub(): Club
    {
        $instance = Instance::query()->findOrFail($this->context->instanceId());

        return Club::query()->forInstance($instance->id)->findOrFail($instance->club_id);
    }

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $club = $this->managedClub();
        $categories = array_map(fn (TrainingCategory $category): array => [
            'id' => $category->value,
            'key' => strtolower($category->name),
            'name' => $category->name,
            'threshold' => $this->calculator->progressThreshold($category->value),
        ], TrainingCategory::cases());
        $fields = array_merge(PlayerFields::TECHNICAL_FIELDS, PlayerFields::MENTAL_FIELDS, PlayerFields::PHYSICAL_FIELDS, PlayerFields::GOALKEEPING_FIELDS);
        $date = $this->context->instanceDate();
        $players = $this->playersForClub($club)->with('person')
            ->leftJoin('players_progress', 'players_progress.player_id', '=', 'players.id')
            ->select('players.*')
            ->addSelect(['players_progress.condition as training_condition', 'players_progress.last_progressed_at'])
            ->addSelect(array_map(fn (string $field): string => "players_progress.{$field} as progress_{$field}", $fields))
            ->selectSub(DB::table('player_injuries')->selectRaw('1')->whereColumn('player_injuries.player_id', 'players.id')
                ->where('player_injuries.instance_id', $club->instance_id)->where('injury_start_date', '<=', $date)
                ->where('injury_end_date', '>=', $date)->limit(1), 'is_injured')
            ->orderBy('players.id')->get();
        $schedules = DB::table('training_player_schedule')->whereIn('player_id', $players->modelKeys())->get()->groupBy('player_id');

        return [
            'club' => $club->only(['id', 'name']),
            'date' => $date,
            'categories' => $categories,
            'intensities' => array_map(fn (TrainingIntensity $intensity): array => ['id' => $intensity->value, 'name' => $intensity->name], TrainingIntensity::cases()),
            'presets' => $this->presets(),
            'players' => $players->map(function (Player $player) use ($schedules): array {
                $stored = $schedules->get($player->id, collect())->keyBy('training_category_id');
                $schedule = [];
                $scheduleData = [];
                $attributes = [];

                foreach (TrainingCategory::cases() as $category) {
                    $intensity = TrainingIntensity::from((int) ($stored->get($category->value)?->training_intensity_id ?? TrainingIntensity::Medium->value));
                    $key = strtolower($category->name);
                    $schedule[$key] = $intensity->value;
                    $scheduleData[$category->value] = new TrainingScheduleData($category, $intensity);
                    $categoryFields = $category === TrainingCategory::Goalkeeping
                        ? PlayerFields::GOALKEEPING_FIELDS
                        : PlayerDevelopmentCategory::fromTrainingCategoryId($category->value)->fields();
                    $attributes[$key] = array_map(fn (string $field): array => [
                        'key' => $field, 'name' => $this->attributeLabel($field), 'value' => (int) $player->{$field},
                        'progress' => (int) $player->{'progress_'.$field},
                    ], $categoryFields);
                }

                $resting = $this->calculator->isResting($scheduleData, $player->position);
                $condition = (int) ($player->training_condition ?? 100);
                $change = $this->calculator->conditionChange($scheduleData, $player->position);
                $canTrain = ! $player->is_injured && $condition >= PlayerProgressCalculator::MINIMUM_TRAINING_CONDITION;
                $conditionAfterSession = $resting ? $this->calculator->recoveredCondition($condition)
                    : ($canTrain ? min(100, max(PlayerProgressCalculator::MINIMUM_TRAINING_CONDITION, $condition + $change)) : $condition);

                return [
                    'id' => $player->id, 'name' => trim($player->person->first_name.' '.$player->person->last_name),
                    'position' => $player->position, 'condition' => $condition, 'is_injured' => (bool) $player->is_injured,
                    'resting' => $resting, 'last_processed_at' => $player->last_progressed_at,
                    'workload' => $resting ? 'Rest' : (! $canTrain ? 'Unavailable' : ($change < 0 ? 'Heavy' : 'Balanced')),
                    'condition_change' => $conditionAfterSession - $condition,
                    'schedule' => $schedule, 'attributes' => $attributes,
                ];
            })->all(),
        ];
    }

    /**
     * @param  list<int>  $playerIds
     * @param  array{physical: int, tactical: int, technical: int, goalkeeping: int}  $schedule
     */
    public function update(array $playerIds, array $schedule): void
    {
        $club = $this->managedClub();

        DB::transaction(function () use ($club, $playerIds, $schedule): void {
            $players = $this->playersForClub($club)->whereIn('id', $playerIds)->lockForUpdate()->get(['id']);
            abort_unless($players->count() === count($playerIds), 404);
            $rows = [];
            $timestamp = now();

            foreach ($players as $player) {
                foreach (TrainingCategory::cases() as $category) {
                    $rows[] = [
                        'player_id' => $player->id, 'training_category_id' => $category->value,
                        'training_intensity_id' => $schedule[strtolower($category->name)],
                        'created_at' => $timestamp, 'updated_at' => $timestamp,
                    ];
                }
            }

            DB::table('training_player_schedule')->upsert($rows, ['player_id', 'training_category_id'], ['training_intensity_id', 'updated_at']);
        });
    }

    private function playersForClub(Club $club): Builder
    {
        return Player::query()->where('players.instance_id', $club->instance_id)
            ->where('players.club_id', $club->id)->where('players.is_retired', false);
    }

    private function attributeLabel(string $field): string
    {
        return $field === 'freeKick' ? 'Free kicks' : ucfirst(str_replace('_', ' ', $field));
    }

    /** @return list<array{id: string, name: string, goalkeeper_only: bool, schedule: array<string, int>}> */
    private function presets(): array
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
