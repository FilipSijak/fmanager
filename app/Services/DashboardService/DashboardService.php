<?php

namespace App\Services\DashboardService;

use App\Models\Account;
use App\Models\Club;
use App\Models\Game;
use App\Models\Instance;
use App\Services\NewsService\NewsService;
use App\Support\GameContext;

class DashboardService
{
    public function __construct(
        private readonly GameContext $gameContext,
        private readonly NewsService $newsService,
    )
    {
    }

    public function getDashboard(): DashboardData
    {
        $instance = Instance::findOrFail($this->gameContext->instanceId());
        $club = Club::findOrFail($instance->club_id);
        $account  = Account::where('club_id', $instance->club_id)->first();
        $news = $this->newsService->getInboxNews($club->id);
        $nextMatch = Game::query()
            ->select([
                'games.*',
                'home_team.name as home_team_name',
                'away_team.name as away_team_name',
            ])
            ->join('clubs as home_team', 'games.hometeam_id', '=', 'home_team.id')
            ->join('clubs as away_team', 'games.awayteam_id', '=', 'away_team.id')
            ->where('games.instance_id', $instance->id)
            ->where('games.match_start', '>=', $instance->instance_date)
            ->forClub($club->id)
            ->orderBy('games.match_start')
            ->first();

        return new DashboardData(
            instance: $instance,
            club: $club,
            account: $account,
            news: $news,
            nextMatch: $nextMatch,
        );
    }
}
