<?php

namespace App\Repositories;

use App\Models\Club;
use App\Models\Game;
use App\Services\TrainingService\Data\ScheduledGameData;
use App\Services\TrainingService\Data\TrainingPlayerData;
use App\Services\TrainingService\Data\TrainingScheduleData;
use App\Services\TrainingService\TrainingCategory;
use App\Services\TrainingService\TrainingIntensity;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrainingRepository
{
    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function playersForTraining(
        Club $club,
        array $trainingFields,
        CarbonInterface $trainingDate
    ): Collection {
        $trainingDay = $trainingDate->toDateString();

        return DB::table('players')
            ->join('players_progress', 'players_progress.player_id', '=', 'players.id')
            ->where('players.instance_id', $club->instance_id)
            ->where('players.club_id', $club->id)
            ->where('players.is_retired', false)
            ->select([
                'players.id',
                'players.potential',
                'players.max_potential',
                'players.technical',
                'players.mental',
                'players.physical',
                'players.position',
                'players_progress.condition',
                ...array_map(fn (string $field): string => "players.{$field} as player_{$field}", $trainingFields),
                ...array_map(fn (string $field): string => "players_progress.{$field} as progress_{$field}", $trainingFields),
            ])
            ->selectRaw(
                'EXISTS (SELECT 1 FROM player_injuries'
                .' WHERE player_injuries.player_id = players.id'
                .' AND player_injuries.injury_start_date <= ?'
                .' AND player_injuries.injury_end_date >= ?) AS is_injured',
                [$trainingDay, $trainingDay]
            )
            ->lockForUpdate()
            ->get()
            ->map(fn (object $row): TrainingPlayerData => new TrainingPlayerData(
                id: (int) $row->id,
                potential: (int) $row->potential,
                maxPotential: (int) $row->max_potential,
                technical: (int) $row->technical,
                mental: (int) $row->mental,
                physical: (int) $row->physical,
                position: $row->position,
                injured: (bool) $row->is_injured,
                condition: (int) $row->condition,
                attributes: collect($trainingFields)->mapWithKeys(
                    fn (string $field): array => [$field => (int) $row->{"player_{$field}"}]
                )->all(),
                progress: collect($trainingFields)->mapWithKeys(
                    fn (string $field): array => [$field => (int) $row->{"progress_{$field}"}]
                )->all(),
            ));
    }

    public function schedulesForPlayers(Collection $playerIds): Collection
    {
        return DB::table('training_player_schedule')
            ->whereIn('player_id', $playerIds)
            ->select(['player_id', 'training_category_id', 'training_intensity_id'])
            ->lockForUpdate()
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
        return DB::table('games')
            ->where('instance_id', $instanceId)
            ->where('status', '!=', Game::STATUS_CANCELLED)
            ->where('match_start', '>=', $from->startOfDay())
            ->where('match_start', '<', $to->addDay()->startOfDay())
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
        return DB::table('players')
            ->where('instance_id', $club->instance_id)
            ->where('club_id', $club->id)
            ->where('is_retired', false)
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
