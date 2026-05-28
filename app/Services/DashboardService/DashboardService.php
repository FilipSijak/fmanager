<?php

namespace App\Services\DashboardService;

use App\Models\Account;
use App\Models\Club;
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
    public function getDashboard()
    {
        $instance = Instance::findOrFail($this->gameContext->instanceId());
        $club = Club::findOrFail($instance->club_id);
        $account  = Account::where('club_id', $instance->club_id)->first();
        $unreadNews = $this->newsService->getInboxNews($club->id);

        return [
            'instance' => [
                'id' => $instance->id,
                'date' => $instance->instance_date,
                'season_id' => $instance->season_id,
            ],
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'rank' => $club->rank,
                'rank_academy' => $club->rank_academy,
                'rank_training' => $club->rank_training,
            ],
            'account' => [
                'balance' => $account?->balance,
                'future_balance' => $account?->future_balance,
                'transfer_budget' => $account?->transfer_budget,
                'salaries_yearly_budget' => $account?->salaries_yearly_budget,
            ],
            'news' => [
                'unread_count' => $unreadNews->count(),
                'latest' => $unreadNews->take(5)->values()->toArray(),
            ],
        ];
    }
}
