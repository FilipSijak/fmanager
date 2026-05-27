<?php

namespace App\Events\Transfers;

use App\Models\Transfer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransferEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly TransferEventType $type,
        public readonly Transfer $transfer,
    )
    {

    }
}
