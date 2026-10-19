<?php

namespace App\Services\NewsService;

use App\Models\News;
use App\Support\GameContext;

class NewsService
{
    public function __construct(
        private readonly GameContext $gameContext,
    ) {}

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

    public function getInboxNews(int $clubId, int $limit = 30)
    {
        $query = News::query()
            ->forInstance($this->gameContext->instanceId())
            ->where(function ($query) use ($clubId) {
                $query->where('club_id', $clubId)
                    ->orWhereNull('club_id');
            })
            ->inboxOrder();

        return $query->limit($limit)->get();
    }

    /**
     * Inbox for a manager without a club: general news plus news about the league they follow.
     */
    public function getCompetitionInboxNews(?int $competitionId, int $limit = 30)
    {
        $query = News::query()
            ->forInstance($this->gameContext->instanceId())
            ->whereNull('club_id')
            ->where(function ($query) use ($competitionId) {
                $query->whereNull('competition_id')
                    ->when($competitionId !== null, fn ($query) => $query->orWhere('competition_id', $competitionId));
            })
            ->inboxOrder();

        return $query->limit($limit)->get();
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
