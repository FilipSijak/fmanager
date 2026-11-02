<?php

namespace App\Repositories;

use App\Domain\PlayerDevelopment\TrainingCategory;
use App\Models\Club;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Player;
use App\Services\TrainingService\Data\ScheduledGameData;
use App\Services\TrainingService\Data\TrainingPlayerData;
use App\Services\TrainingService\Data\TrainingScheduleData;
use App\Services\TrainingService\TrainingIntensity;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrainingRepository
{
    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function managedClub(int $instanceId): Club
    {
        $instance = Instance::query()->findOrFail($instanceId);

        return Club::query()->forInstance($instanceId)->findOrFail($instance->club_id);
    }

    /**
     * @param  list<string>  $trainingFields
     * @return Collection<int, Player>
     */
    public function playersForOverview(Club $club, array $trainingFields, string $date): Collection
    {
        return Player::query()->forClub($club)->active()
            ->with('person')->withTrainingProgress($trainingFields)->withInjuryStatusOn($date)
            ->orderBy('players.id')->get();
    }

    /**
     * @param  list<string>  $trainingFields
     * @return Collection<int, TrainingPlayerData>
     */
    public function playersForTraining(
        Club $club,
        array $trainingFields,
        CarbonInterface $trainingDate
    ): Collection {
        return Player::query()->forClub($club)->active()
            ->withTrainingProgress($trainingFields)->withInjuryStatusOn($trainingDate->toDateString())
            ->whereNotNull('players_progress.player_id')->lockForUpdate()->get()
            ->map(fn (Player $player): TrainingPlayerData => TrainingPlayerData::fromPlayer($player, $trainingFields));
    }

    /**
     * @param  list<int>  $playerIds
     * @return Collection<int, Player>
     */
    public function activePlayersByIdsForUpdate(Club $club, array $playerIds): Collection
    {
        return Player::query()->forClub($club)->active()->whereIn('id', $playerIds)->lockForUpdate()->get(['id']);
    }

    /** @param list<array{player_id: int, training_category_id: int, training_intensity_id: int}> $rows */
    public function saveSchedules(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $timestamp = now();
        $rows = array_map(fn (array $row): array => $row + ['created_at' => $timestamp, 'updated_at' => $timestamp], $rows);
        DB::table('training_player_schedule')->upsert($rows, ['player_id', 'training_category_id'], ['training_intensity_id', 'updated_at']);
    }

    public function schedulesForPlayers(Collection $playerIds, bool $lockForUpdate = true): Collection
    {
        return DB::table('training_player_schedule')
            ->whereIn('player_id', $playerIds)
            ->select(['player_id', 'training_category_id', 'training_intensity_id'])
            ->when($lockForUpdate, fn (Builder $query): Builder => $query->lockForUpdate())
            ->get()
            ->groupBy('player_id')
            ->map(fn (Collection $schedules): array => $schedules
                ->mapWithKeys(fn (object $schedule): array => [
                    (int) $schedule->training_category_id => new TrainingScheduleData(
                        TrainingCategory::from((int) $schedule->training_category_id),
                        TrainingIntensity::from((int) $schedule->training_intensity_id),
                    ),
                ])
                ->all());
    }

    public function scheduledGames(int $instanceId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Game::query()->forInstance($instanceId)->notCancelled()
            ->startingBetween($from->startOfDay(), $to->addDay()->startOfDay())
            ->get(['hometeam_id', 'awayteam_id', 'match_start'])
            ->map(fn (object $game): ScheduledGameData => new ScheduledGameData(
                (int) $game->hometeam_id,
                (int) $game->awayteam_id,
                CarbonImmutable::parse($game->match_start),
            ));
    }

    public function clubsByIds(int $instanceId, Collection $clubIds): Collection
    {
        return Club::query()->forInstance($instanceId)->whereIn('id', $clubIds)->get();
    }

    public function clubsExceptIds(int $instanceId, Collection $clubIds): Collection
    {
        return Club::query()->forInstance($instanceId)->whereNotIn('id', $clubIds)->get();
    }

    public function bulkUpdateProgress(array $updatesByPlayerId): void
    {
        $this->bulkUpdateByKey('players_progress', 'player_id', $updatesByPlayerId);
    }

    public function bulkUpdatePlayers(array $updatesByPlayerId): void
    {
        $this->bulkUpdateByKey('players', 'id', $updatesByPlayerId);
    }

    public function activePlayerIds(Club $club): Collection
    {
        return Player::query()->forClub($club)->active()
            ->lockForUpdate()
            ->pluck('id');
    }

    public function conditionProgressForPlayers(Collection $playerIds): Collection
    {
        return DB::table('players_progress')
            ->whereIn('player_id', $playerIds)
            ->lockForUpdate()
            ->get(['player_id', 'condition']);
    }

    private function bulkUpdateByKey(string $table, string $key, array $updatesById): void
    {
        $groups = [];

        foreach ($updatesById as $id => $updates) {
            if ($updates === []) {
                continue;
            }

            $columns = array_keys($updates);
            sort($columns);
            $signature = implode('|', $columns);
            $groups[$signature]['columns'] = $columns;
            $groups[$signature]['rows'][] = [$key => (int) $id] + $updates;
        }

        foreach ($groups as $group) {
            DB::table($table)->upsert($group['rows'], [$key], $group['columns']);
        }
    }
}
