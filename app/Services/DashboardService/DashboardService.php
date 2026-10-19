<?php

namespace App\Services\DashboardService;

use App\Models\Account;
use App\Models\Club;
use App\Models\Game;
use App\Models\Instance;
use App\Models\StaffCoaching;
use App\Services\NewsService\NewsService;
use App\Support\GameContext;

class DashboardService
{
    public function __construct(
        private readonly GameContext $gameContext,
        private readonly NewsService $newsService,
    ) {}

    public function getDashboard(): DashboardData
    {
        $instance = Instance::query()->with('followedCompetition')->findOrFail($this->gameContext->instanceId());
        $club = $instance->club_id === null ? null : Club::findOrFail($instance->club_id);
        $manager = StaffCoaching::query()->with('person')->find($instance->manager_id);
        $account = $club === null ? null : Account::where('club_id', $club->id)->first();
        $news = $club === null
            ? $this->newsService->getCompetitionInboxNews($instance->followed_competition_id)
            : $this->newsService->getInboxNews($club->id);
        $nextGame = Game::query()
            ->select([
                'games.*',
                'home_team.name as home_team_name',
                'away_team.name as away_team_name',
            ])
            ->join('clubs as home_team', 'games.hometeam_id', '=', 'home_team.id')
            ->join('clubs as away_team', 'games.awayteam_id', '=', 'away_team.id')
            ->where('games.instance_id', $instance->id)
            ->where('games.match_start', '>=', $instance->instance_date)
            ->when(
                $club !== null,
                fn ($query) => $query->forClub($club->id),
                fn ($query) => $query->where('games.competition_id', $instance->followed_competition_id),
            )
            ->orderBy('games.match_start')
            ->first();

        return new DashboardData(
            instance: $instance,
            club: $club,
            followedCompetition: $instance->followedCompetition,
            manager: $manager,
            account: $account,
            news: $news,
            nextGame: $nextGame,
        );
    }
}
