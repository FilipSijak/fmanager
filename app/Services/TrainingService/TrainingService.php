<?php

namespace App\Services\TrainingService;

use App\Models\Club;
use App\Models\Instance;
use App\Repositories\TrainingRepository;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use Carbon\CarbonImmutable;

class TrainingService
{
    public function __construct(
        private readonly TrainingRepository $trainingRepository,
        private readonly PlayerProgressCalculator $playerProgressCalculator
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

    private function executeTrainingSession(Club $club, CarbonImmutable $trainingDate): void
    {
        $trainingFields = array_merge(
            PlayerFields::TECHNICAL_FIELDS,
            PlayerFields::MENTAL_FIELDS,
            PlayerFields::PHYSICAL_FIELDS
        );

        $this->trainingRepository->transaction(function () use ($club, $trainingDate, $trainingFields): void {
            $players = $this->trainingRepository->playersForTraining($club, $trainingFields, $trainingDate);
            $playerIds = $players->pluck('id');
            $schedulesByPlayer = $this->trainingRepository->schedulesForPlayers($playerIds);

            foreach ($players as $player) {
                $updates = $this->playerProgressCalculator->forTrainingSession(
                    $player,
                    $schedulesByPlayer->get($player->id, []),
                    $trainingFields,
                    $trainingDate
                );
                $this->trainingRepository->updateProgress((int) $player->id, $updates->progress);

                if ($updates->player !== []) {
                    $this->trainingRepository->updatePlayer((int) $player->id, $updates->player);
                }

            }

        });
    }

    private function recoverCondition(Club $club, CarbonImmutable $trainingDate): void
    {
        $this->trainingRepository->transaction(function () use ($club, $trainingDate): void {
            $playerIds = $this->trainingRepository->activePlayerIds($club);
            $progressRows = $this->trainingRepository->conditionProgressForPlayers($playerIds);
            foreach ($progressRows as $progress) {
                $condition = $this->playerProgressCalculator->recoveredCondition((int) $progress->condition);

                if ($condition === (int) $progress->condition) {
                    continue;
                }

                $this->trainingRepository->updateProgress((int) $progress->player_id, [
                    'condition' => $condition,
                    'updated_at' => $trainingDate,
                ]);
            }

        });
    }
}
