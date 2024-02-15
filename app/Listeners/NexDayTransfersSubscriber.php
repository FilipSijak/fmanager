<?php

namespace App\Listeners;

use App\Events\NextDay;
use App\Services\TransferService\TransferService;

class NexDayTransfersSubscriber
{
    private TransferService $transferService;

    public function __construct(
        TransferService $transferService
    )
    {
        $this->transferService = $transferService;
    }

    public function handleProcessTransferRequests()
    {
        $this->transferService->processTransferBids();
    }

    public function handleProcessTransferringPlayers($event)
    {

    }

    public function subscribe($events)
    {
        $events->listen(
            'App\Events\NextDay',
            'App\Listeners\NexDayTransfersSubscriber@handleProcessTransferRequests'
        );

        $events->listen(
            'App\Events\NextDay',
            'App\Listeners\NexDayTransfersSubscriber@handleProcessTransferringPlayers'
        );
    }
}
