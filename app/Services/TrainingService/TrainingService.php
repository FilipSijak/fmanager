<?php

namespace App\Services\TrainingService;

use App\Models\Club;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use Illuminate\Support\Facades\DB;

class TrainingService
{
    private const MINIMUM_DEVELOPMENT_GAP = 10;

    private const GAP_PER_POINT = 10;

    private const MAX_POINTS_PER_SESSION = 3;

    private const PROGRESS_THRESHOLD = 100;

    private const MISSED_SESSION_PENALTY = 2;

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
                    ...$trainingFields,
                ])
                ->lockForUpdate()
                ->get();

            $progressByPlayer = DB::table('players_progress')
                ->whereIn('player_id', $players->pluck('id'))
                ->select(['player_id', ...$trainingFields])
                ->lockForUpdate()
                ->get()
                ->keyBy('player_id');

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

                $points = $this->pointsForPotentialGap(
                    (int) $player->max_potential - (int) $player->potential
                );

                if ($points === 0) {
                    continue;
                }

                $playerUpdates = [];
                $progressUpdates = ['last_progressed_at' => now(), 'updated_at' => now()];

                foreach ($trainingFields as $field) {
                    $totalProgress = (int) $progress->{$field} + $points;
                    $attributeIncrease = intdiv($totalProgress, self::PROGRESS_THRESHOLD);
                    $progressUpdates[$field] = $totalProgress % self::PROGRESS_THRESHOLD;

                    if ($attributeIncrease > 0) {
                        $playerUpdates[$field] = (int) $player->{$field} + $attributeIncrease;
                    }
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

    private function pointsForPotentialGap(int $gap): int
    {
        if ($gap < self::MINIMUM_DEVELOPMENT_GAP) {
            return 0;
        }

        return min(self::MAX_POINTS_PER_SESSION, intdiv($gap, self::GAP_PER_POINT));
    }
}
