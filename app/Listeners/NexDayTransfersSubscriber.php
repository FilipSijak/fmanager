<?php

namespace App\Listeners;

use App\Services\TransferService\TransferService;
use App\Services\TransferService\TransferWindowConfig\TransferWindowAvailability;
use Carbon\Carbon;

class NexDayTransfersSubscriber
{
    private TransferService $transferService;

    public function __construct(
        TransferService $transferService,
        TransferWindowAvailability $transferWindowAvailability
    )
    {
        $this->transferService = $transferService;
        $this->transferWindowAvailability = $transferWindowAvailability;
    }

    /** Daily process of every transfer bid */
    public function handleTranferBids()
    {
        $this->transferService->processTransferBids();
    }

    public function handleProcessTransferringPlayers($event)
    {

    }

    /** Check all clubs if they want/need to buy players */
    public function handleTransferRequests($event)
    {
        //within transfer window, check every week if clubs want to buy players
        if ($this->transferWindowAvailability->isTransferWindowOpen($event->instance->instance_date) &&
            Carbon::parse($event->instance->instance_date)->startOfWeek()->format('Y-m-d') ==
            $event->instance->instance_date
        ) {
            // check all clubs what they need
            $this->transferService->automaticTransferBids($event->instance);
        }

        //outside the transfer window, check every month if clubs want to buy players
        if (!$this->transferWindowAvailability->isTransferWindowOpen($event->instance->instance_date) &&
            Carbon::parse($event->instance->instance_date)->startOfMonth()->format('Y-m-d') ==
            $event->instance->instance_date
        ) {
            // check all clubs what they need
            $this->transferService->automaticTransferBids($event->instance);
        }
    }

    public function subscribe($events)
    {
        $events->listen(
            'App\Events\NextDay',
            'App\Listeners\NexDayTransfersSubscriber@handleTranferBids'
        );

        $events->listen(
            'App\Events\NextDay',
            'App\Listeners\NexDayTransfersSubscriber@handleProcessTransferringPlayers'
        );

        $events->listen(
            'App\Events\NextDay',
            'App\Listeners\NexDayTransfersSubscriber@handleTransferRequests'
        );
    }
}
