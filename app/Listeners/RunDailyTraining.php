<?php

namespace App\Listeners;

use App\Events\NextDay;
use App\Models\Club;
use App\Models\Game;
use App\Services\TrainingService\TrainingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class RunDailyTraining
{
    public function __construct(private readonly TrainingService $trainingService) {}

    public function handle(NextDay $event): void
    {
        $trainingDate = CarbonImmutable::parse($event->instance->instance_date);
        $previousDate = $trainingDate->subDay();

        $games = DB::table('games')
            ->where('instance_id', $event->instance->id)
            ->where('status', '!=', Game::STATUS_CANCELLED)
            ->where(function ($query) use ($trainingDate, $previousDate): void {
                $query->whereDate('match_start', $trainingDate)
                    ->orWhereDate('match_start', $previousDate);
            })
            ->get(['hometeam_id', 'awayteam_id']);

        $restingClubIds = $games
            ->flatMap(fn ($game): array => [$game->hometeam_id, $game->awayteam_id])
            ->unique()
            ->values();

        Club::query()
            ->forInstance($event->instance->id)
            ->whereNotIn('id', $restingClubIds)
            ->each(fn (Club $club) => $this->trainingService->executeTrainingSession($club));
    }
}
