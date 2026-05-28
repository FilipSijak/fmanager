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
            TransferEventType::DelayedUntilWindow => $this->transferNews->delayedUntilWindow($event->transfer),
            TransferEventType::AffordabilityFailed => $this->transferNews->affordabilityFailed($event->transfer),
            TransferEventType::SellingClubAccepted => $this->transferNews->sellingClubAccepted($event->transfer),
            TransferEventType::SellingClubCountered => $this->transferNews->sellingClubCountered($event->transfer),
            TransferEventType::SellingClubDeclined => $this->transferNews->sellingClubDeclined($event->transfer),
            TransferEventType::CounterofferAccepted => $this->transferNews->counterofferAccepted($event->transfer),
            TransferEventType::CounterofferRejected => $this->transferNews->counterofferRejected($event->transfer),
            TransferEventType::PlayerAccepted => $this->transferNews->playerAccepted($event->transfer),
            TransferEventType::PlayerCountered => $this->transferNews->playerCountered($event->transfer),
            TransferEventType::PlayerCounterofferAccepted => $this->transferNews->playerCounterofferAccepted($event->transfer),
            TransferEventType::PlayerCounterofferRejected => $this->transferNews->playerCounterofferRejected($event->transfer),
            TransferEventType::PlayerDeclined => $this->transferNews->playerDeclined($event->transfer),
            TransferEventType::TargetClubDeclined => $this->transferNews->targetClubDeclined($event->transfer),
        };

        $this->newsService->publish($item);
    }
}
