<?php

namespace App\Listeners\News;

use App\Events\Transfers\TransferEvent;
use App\Events\Transfers\TransferEventType;
use App\Services\NewsService\NewsService;
use App\Services\NewsService\NewsTypes\TransferNews;

class CreateTransferNews
{
    public function __construct(
        private readonly NewsService $newsService,
        private readonly TransferNews $transferNews,
    ) {
    }

    public function handle(TransferEvent $event): void
    {
        $item = match ($event->type) {
            TransferEventType::Completed => $this->transferNews->completed($event->transfer),
            TransferEventType::MedicalFailed => $this->transferNews->medicalFailed($event->transfer),
        };

        $this->newsService->publish($item);
    }
}
