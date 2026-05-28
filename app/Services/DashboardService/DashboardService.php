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
            'instance' => $instance,
            'club' => $club,
            'account' => $account,
            'unread_news' => $unreadNews,
        ];
    }
}
