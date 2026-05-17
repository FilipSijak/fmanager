<?php

namespace App\Exceptions;

use DomainException;

class InvalidTransferTransition extends DomainException
{
    public function __construct(int $currentStatus, int $nextStatus)
    {
        parent::__construct("Invalid transfer status transition from {$currentStatus} to {$nextStatus}.");
    }
}
