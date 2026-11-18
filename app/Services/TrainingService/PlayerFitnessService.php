<?php

namespace App\Services\TrainingService;

use App\Models\Game;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerInjury;
use App\Repositories\PlayerFitnessRepository;
use App\Services\NewsService\NewsService;
use App\Services\NewsService\NewsTypes\PlayerInjuryNews;
use App\Services\TrainingService\Data\TrainingPlayerData;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Random\Randomizer;

class PlayerFitnessService
{
    public function __construct(
        private readonly PlayerFitnessRepository $repository,
        private readonly PlayerFitnessCalculator $calculator,
        private readonly NewsService $news,
        private readonly PlayerInjuryNews $reports,
        private readonly Randomizer $randomizer = new Randomizer,
    ) {}

    public function completeMatch(Game $game): void
    {
        $date = CarbonImmutable::parse($game->match_start)->startOfDay();
        $players = $this->repository->participants($game);
        foreach ($game->playerStats()->where('minutes_played', '>', 0)->get() as $stats) {
            $player = $players->get($stats->player_id);
            if ($player === null) {
                continue;
            }
            $this->repository->storeCondition($player, $this->calculator->conditionAfterMatch(
                (int) ($player->training_condition ?? 100), $stats->minutes_played, (int) $player->stamina, $stats->position, (int) $player->workrate, (int) $player->teamwork,
            ));
        }
        $summary = json_decode($game->match_summary ?? '{}', true, flags: JSON_THROW_ON_ERROR);
        foreach ($summary['events'] ?? [] as $event) {
            if (($event['type'] ?? null) !== 'injury' || ! $players->has($event['player_id'])) {
                continue;
            }
            $this->recordInjury($event['player_id'], $game->instance_id, $game->season_id, $date, $event['injury_days']);
        }
    }

    /** @param array<int, Data\TrainingScheduleData> $schedules */
    public function train(TrainingPlayerData $player, array $schedules, Instance $instance, CarbonInterface $date): void
    {
        if ($player->injured || $player->condition < PlayerProgressCalculator::MINIMUM_TRAINING_CONDITION) {
            return;
        }
        $risk = $this->calculator->trainingInjuryProbability($player->condition, $player->position, $schedules);
        if ($risk > 0 && $this->randomizer->nextFloat() < $risk) {
            $this->recordInjury($player->id, $instance->id, $instance->season_id, $date, $this->randomizer->getInt(3, 28));
        }
    }

    public function reportRecoveries(Instance $instance): void
    {
        $date = CarbonImmutable::parse($instance->instance_date)->startOfDay();
        DB::transaction(function () use ($instance, $date): void {
            foreach ($this->repository->recoveredInjuries($instance, $date) as $injury) {
                $player = $injury->player;
                if (! PlayerInjury::query()->forPlayer($player)->activeOn($date->toDateString())->exists()) {
                    $this->publishReport($instance, $player, $injury, $date, true);
                }
                $injury->forceFill(['recovery_reported_on' => $date->toDateString()])->save();
            }
        });
    }

    private function recordInjury(int $playerId, int $instanceId, int $seasonId, CarbonInterface $date, int $days): void
    {
        $injury = $this->repository->injure($playerId, $instanceId, $seasonId, $date, $days);
        if ($injury !== null) {
            $this->publishReport(Instance::query()->findOrFail($instanceId), $injury->player, $injury, $date);
        }
    }

    private function publishReport(Instance $instance, Player $player, PlayerInjury $injury, CarbonInterface $date, bool $recovered = false): void
    {
        if ($instance->club_id !== null && $instance->club_id === $player->club_id) {
            $this->news->publish($this->reports->report($instance, $player, $injury, $date, $recovered));
        }
    }
}
