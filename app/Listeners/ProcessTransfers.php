<?php

namespace App\Listeners;

use App\Events\NextDay;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProcessTransfers
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\NextDay  $event
     * @return void
     */
    public function handle(NextDay $event)
    {

    }
}
