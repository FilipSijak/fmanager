<?php

namespace App\Repositories\Competition;

use App\Models\Game;
use App\Models\Instance;
use Illuminate\Database\Eloquent\Collection;

final class CompetitionScheduleRepository
{
    /** @return Collection<int, Game> */
    public function scheduledFor(Instance $instance): Collection
    {
        return Game::query()->where('instance_id', $instance->id)
            ->whereDate('match_start', $instance->instance_date)->whereNull('processed_at')
            ->whereIn('status', [Game::STATUS_SCHEDULED, Game::STATUS_POSTPONED])->get();
    }
}
