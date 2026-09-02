<?php

namespace App\Repositories;

use App\Models\Club;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TrainingRepository
{
    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function trainingDate(int $instanceId): string
    {
        $trainingDate = DB::table('instances')->where('id', $instanceId)->value('instance_date');

        if ($trainingDate === null) {
            throw new RuntimeException("Instance {$instanceId} does not exist.");
        }

        return (string) $trainingDate;
    }

    public function playersForTraining(
        Club $club,
        array $trainingFields,
        string $trainingDate
    ): Collection {
        $activeInjuries = DB::table('player_injuries')
            ->select('player_id')
            ->whereDate('injury_start_date', '<=', $trainingDate)
            ->whereDate('injury_end_date', '>=', $trainingDate)
            ->distinct();

        return DB::table('players')
            ->leftJoinSub($activeInjuries, 'active_injuries', function ($join): void {
                $join->on('active_injuries.player_id', '=', 'players.id');
            })
            ->where('players.instance_id', $club->instance_id)
            ->where('players.club_id', $club->id)
            ->where('players.is_retired', false)
            ->select([
                'players.id',
                'players.potential',
                'players.max_potential',
                'players.physical',
                'players.position',
                DB::raw('active_injuries.player_id IS NOT NULL AS is_injured'),
                ...$trainingFields,
            ])
            ->lockForUpdate()
            ->get();
    }

    public function progressForPlayers(Collection $playerIds, array $trainingFields): Collection
    {
        return DB::table('players_progress')
            ->whereIn('player_id', $playerIds)
            ->select(['player_id', 'condition', ...$trainingFields])
            ->lockForUpdate()
            ->get()
            ->keyBy('player_id');
    }

    public function schedulesForPlayers(Collection $playerIds): Collection
    {
        return DB::table('training_player_schedule')
            ->whereIn('player_id', $playerIds)
            ->select(['player_id', 'training_category_id', 'training_intensity_id'])
            ->lockForUpdate()
            ->get()
            ->groupBy('player_id')
            ->map(fn ($schedules) => $schedules->keyBy('training_category_id'));
    }

    public function updateProgress(int $playerId, array $updates): void
    {
        DB::table('players_progress')->where('player_id', $playerId)->update($updates);
    }

    public function updatePlayer(int $playerId, array $updates): void
    {
        DB::table('players')->where('id', $playerId)->update($updates);
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
}
