<?php

namespace App\Services\TrainingService;

use App\Domain\PlayerDevelopment\PlayerDevelopmentCategory;
use App\Domain\PlayerDevelopment\TrainingCategory;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Repositories\TrainingRepository;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use App\Support\GameContext;
use Carbon\CarbonImmutable;

class TrainingService
{
    public function __construct(
        private readonly TrainingRepository $trainingRepository,
        private readonly PlayerProgressCalculator $playerProgressCalculator,
        private readonly GameContext $context,
    ) {}

    public function processDay(Instance $instance): void
    {
        $trainingDate = CarbonImmutable::parse($instance->instance_date)->startOfDay();
        $previousDate = $trainingDate->subDay();
        $games = $this->trainingRepository->scheduledGames(
            (int) $instance->id,
            $previousDate,
            $trainingDate
        );
        $clubIds = static fn ($scheduledGames) => $scheduledGames
            ->flatMap(fn ($game): array => $game->clubIds())
            ->unique()
            ->values();
        $playingTodayIds = $clubIds($games->filter(
            fn ($game): bool => $game->kickoff->isSameDay($trainingDate)
        ));
        $postMatchRestIds = $clubIds($games->filter(
            fn ($game): bool => $game->kickoff->isSameDay($previousDate)
        ))->diff($playingTodayIds)->values();
        $clubsWithoutTraining = $playingTodayIds->merge($postMatchRestIds)->unique();

        $this->trainingRepository->clubsByIds((int) $instance->id, $postMatchRestIds)
            ->each(fn (Club $club) => $this->recoverCondition($club, $trainingDate));
        $this->trainingRepository->clubsExceptIds((int) $instance->id, $clubsWithoutTraining)
            ->each(fn (Club $club) => $this->executeTrainingSession($club, $trainingDate));
    }

    public function managedClub(): Club
    {
        return $this->trainingRepository->managedClub($this->context->instanceId());
    }

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $club = $this->managedClub();
        $categories = array_map(fn (TrainingCategory $category): array => [
            'id' => $category->value,
            'key' => strtolower($category->name),
            'name' => $category->name,
            'threshold' => $this->playerProgressCalculator->progressThreshold($category->value),
        ], TrainingCategory::cases());
        $fields = array_merge(PlayerFields::TECHNICAL_FIELDS, PlayerFields::MENTAL_FIELDS, PlayerFields::PHYSICAL_FIELDS, PlayerFields::GOALKEEPING_FIELDS);
        $date = $this->context->instanceDate();
        $players = $this->trainingRepository->playersForOverview($club, $fields, $date);
        $schedules = $this->trainingRepository->schedulesForPlayers($players->pluck('id'), false);

        return [
            'club' => $club->only(['id', 'name']),
            'date' => $date,
            'categories' => $categories,
            'intensities' => array_map(fn (TrainingIntensity $intensity): array => ['id' => $intensity->value, 'name' => $intensity->name], TrainingIntensity::cases()),
            'presets' => TrainingPlan::presets(),
            'players' => $players->map(function (Player $player) use ($schedules): array {
                $plan = TrainingPlan::fromSchedules($schedules->get($player->id, []));
                $scheduleData = $plan->schedules();
                $attributes = [];

                foreach (TrainingCategory::cases() as $category) {
                    $key = strtolower($category->name);
                    $categoryFields = $category === TrainingCategory::Goalkeeping
                        ? PlayerFields::GOALKEEPING_FIELDS
                        : PlayerDevelopmentCategory::fromTrainingCategoryId($category->value)->fields();
                    $attributes[$key] = array_map(fn (string $field): array => [
                        'key' => $field, 'name' => $this->attributeLabel($field), 'value' => (int) $player->{$field},
                        'progress' => (int) $player->{'progress_'.$field},
                    ], $categoryFields);
                }

                $resting = $this->playerProgressCalculator->isResting($scheduleData, $player->position);
                $condition = (int) ($player->training_condition ?? 100);
                $change = $this->playerProgressCalculator->conditionChange($scheduleData, $player->position);
                $canTrain = ! $player->is_injured && $condition >= PlayerProgressCalculator::MINIMUM_TRAINING_CONDITION;
                $conditionAfterSession = $resting ? $this->playerProgressCalculator->recoveredCondition($condition)
                    : ($canTrain ? min(100, max(PlayerProgressCalculator::MINIMUM_TRAINING_CONDITION, $condition + $change)) : $condition);

                return [
                    'id' => $player->id, 'name' => trim($player->person->first_name.' '.$player->person->last_name),
                    'position' => $player->position, 'condition' => $condition, 'is_injured' => (bool) $player->is_injured,
                    'resting' => $resting, 'last_processed_at' => $player->last_progressed_at,
                    'workload' => $resting ? 'Rest' : (! $canTrain ? 'Unavailable' : ($change < 0 ? 'Heavy' : 'Balanced')),
                    'condition_change' => $conditionAfterSession - $condition,
                    'schedule' => $plan->schedule, 'attributes' => $attributes,
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

        $this->trainingRepository->transaction(function () use ($club, $playerIds, $schedule): void {
            $players = $this->trainingRepository->activePlayersByIdsForUpdate($club, $playerIds);
            abort_unless($players->count() === count($playerIds), 404);
            $plan = new TrainingPlan($schedule);

            $this->trainingRepository->saveSchedules($plan->rowsForPlayers($players->pluck('id')->all()));
        });
    }

    private function executeTrainingSession(Club $club, CarbonImmutable $trainingDate): void
    {
        $trainingFields = array_merge(
            PlayerFields::TECHNICAL_FIELDS,
            PlayerFields::MENTAL_FIELDS,
            PlayerFields::PHYSICAL_FIELDS,
            PlayerFields::GOALKEEPING_FIELDS
        );

        $this->trainingRepository->transaction(function () use ($club, $trainingDate, $trainingFields): void {
            $players = $this->trainingRepository->playersForTraining($club, $trainingFields, $trainingDate);
            $playerIds = $players->pluck('id');
            $schedulesByPlayer = $this->trainingRepository->schedulesForPlayers($playerIds);
            $progressUpdates = [];
            $playerUpdates = [];

            foreach ($players as $player) {
                $updates = $this->playerProgressCalculator->forTrainingSession(
                    $player,
                    $schedulesByPlayer->get($player->id, []),
                    $trainingFields,
                    $trainingDate
                );
                $progressUpdates[(int) $player->id] = $updates->progress;

                if ($updates->player !== []) {
                    $playerUpdates[(int) $player->id] = $updates->player;
                }

            }

            $this->trainingRepository->bulkUpdateProgress($progressUpdates);
            $this->trainingRepository->bulkUpdatePlayers($playerUpdates);
        });
    }

    private function recoverCondition(Club $club, CarbonImmutable $trainingDate): void
    {
        $this->trainingRepository->transaction(function () use ($club, $trainingDate): void {
            $playerIds = $this->trainingRepository->activePlayerIds($club);
            $progressRows = $this->trainingRepository->conditionProgressForPlayers($playerIds);
            $progressUpdates = [];

            foreach ($progressRows as $progress) {
                $condition = $this->playerProgressCalculator->recoveredCondition((int) $progress->condition);

                if ($condition === (int) $progress->condition) {
                    continue;
                }

                $progressUpdates[(int) $progress->player_id] = [
                    'condition' => $condition,
                    'updated_at' => $trainingDate,
                ];
            }

            $this->trainingRepository->bulkUpdateProgress($progressUpdates);
        });
    }

    private function attributeLabel(string $field): string
    {
        return $field === 'freeKick' ? 'Free kicks' : ucfirst(str_replace('_', ' ', $field));
    }
}
