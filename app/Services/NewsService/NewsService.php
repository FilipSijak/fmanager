<?php

namespace App\Services\NewsService;

use App\Models\News;
use App\Support\GameContext;

class NewsService
{
    public function __construct(
        private readonly GameContext $gameContext,
    )
    {
    }

    public function publish(NewsItem $item): News
    {
        return News::create($item->toDatabasePayload());
    }

    public function getNews(bool $unreadOnly = true)
    {
        $query = News::query()
            ->forInstance($this->gameContext->instanceId())
            ->inboxOrder();

        if ($unreadOnly) {
            $query->unread();
        }

        return $query->get();
    }

    public function markAsRead(int $newsId): News
    {
        $news = News::query()
            ->forInstance($this->gameContext->instanceId())
            ->findOrFail($newsId);

        $news->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return $news;
    }

}
