<?php

namespace App\Repositories;

use App\Models\Game;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerInjury;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlayerFitnessRepository
{
    /** @return Collection<int, Player> */
    public function participants(Game $game): Collection
    {
        return Player::query()->where('players.instance_id', $game->instance_id)
            ->whereIn('club_id', [$game->hometeam_id, $game->awayteam_id])
            ->whereIn('players.id', $game->playerStats()->where('minutes_played', '>', 0)->select('player_id'))
            ->with('person')->withTrainingProgress([])->withInjuryStatusOn(substr($game->match_start, 0, 10))
            ->orderBy('players.id')->lockForUpdate()->get()->keyBy('id');
    }

    public function storeCondition(Player $player, int $condition): void
    {
        DB::table('players_progress')->updateOrInsert(['player_id' => $player->id], [
            'instance_id' => $player->instance_id, 'condition' => $condition, 'updated_at' => now(),
        ]);
    }

    public function injure(int $playerId, int $instanceId, int $seasonId, CarbonInterface $date, int $days): ?PlayerInjury
    {
        $player = Player::query()->forInstance($instanceId)->lockForUpdate()->findOrFail($playerId);
        if (PlayerInjury::query()->forPlayer($player)->activeOn($date->toDateString())->exists()) {
            return null;
        }
        $type = DB::table('injuries')->where('duration_from', '<=', $days)->where('duration_to', '>=', $days)
            ->orderBy('severity')->orderBy('id')->first();
        if ($type === null) {
            return null;
        }

        return PlayerInjury::query()->forceCreate([
            'player_id' => $playerId, 'instance_id' => $instanceId, 'season_id' => $seasonId,
            'injury_id' => $type->id, 'injury_start_date' => $date->toDateString(),
            'injury_end_date' => $date->copy()->addDays($days - 1)->toDateString(),
        ]);
    }

    /** @return Collection<int, PlayerInjury> */
    public function recoveredInjuries(Instance $instance, CarbonInterface $date): Collection
    {
        return PlayerInjury::query()->where('instance_id', $instance->id)
            ->where('injury_end_date', '<', $date->toDateString())->whereNull('recovery_reported_on')
            ->with('player.person')->orderBy('id')->lockForUpdate()->get();
    }
}
