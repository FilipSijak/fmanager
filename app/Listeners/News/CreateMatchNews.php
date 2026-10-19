<?php

namespace App\Listeners\News;

use App\Events\MatchDayCompleted;
use App\Services\NewsService\NewsService;
use App\Services\NewsService\NewsTypes\MatchNews;

class CreateMatchNews
{
    public function __construct(
        private readonly NewsService $newsService,
        private readonly MatchNews $matchNews,
    ) {}

    public function handle(MatchDayCompleted $event): void
    {
        foreach ($this->matchNews->forMatchDay($event->instance, $event->games) as $item) {
            $this->newsService->publish($item);
        }
    }
}
