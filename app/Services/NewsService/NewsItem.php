<?php

namespace App\Services\NewsService;

use Carbon\CarbonInterface;

class NewsItem
{
    public function __construct(
        public int $instanceId,
        public ?int $seasonId,
        public ?int $clubId,
        public ?int $competitionId,
        public string $title,
        public string $content,
        public string $type,
        public NewsPriority $priority = NewsPriority::Normal,
        public ?CarbonInterface $publishedAt = null,
    )
    {
    }

    public function toDatabasePayload(): array
    {
        return [
            'instance_id' => $this->instanceId,
            'season_id' => $this->seasonId,
            'club_id' => $this->clubId,
            'competition_id' => $this->competitionId,
            'title' => $this->title,
            'content' => $this->content,
            'type' => $this->type,
            'priority' => $this->priority->value,
            'published_at' => $this->publishedAt ?? now(),
        ];
    }
}
