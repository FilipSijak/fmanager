<?php

namespace App\Listeners\News;

use App\Events\Transfers\TransferEvent;
use App\Events\Transfers\TransferEventType;
use App\Services\NewsService\NewsService;

class CreateTransferNews
{
    public function __construct(
        private readonly NewsService $newsService
    )
    {

    }

    public function handle(TransferEvent $event): void
    {
        match ($event->type) {
            TransferEventType::Completed => $this->newsService->publishTransferCompleted($event->transfer),
        };
    }
}
