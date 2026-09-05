<?php

namespace App\Services\TrainingService;

use App\Models\Club;
use App\Models\Instance;
use App\Repositories\TrainingRepository;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use Carbon\CarbonImmutable;

class TrainingService
{
    private const REST_DAY_CONDITION_RECOVERY = 10;

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
        $this->trainingRepository->recoverClubCondition(
            $club,
            $trainingDate,
            self::REST_DAY_CONDITION_RECOVERY
        );
    }
}
