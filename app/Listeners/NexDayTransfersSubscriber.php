<?php

namespace App\Listeners;

use App\Events\NextDay;

class NexDayTransfersSubscriber
{
    public function handleProcessTransferRequests()
    {

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
