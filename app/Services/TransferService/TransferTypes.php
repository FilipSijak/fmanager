<?php

namespace App\Services\TransferService;

enum TransferTypes:int
{
    case FREE_TRANSFER = 1;
    case LOAN_TRANSFER = 2;
    case PERMANENT_TRANSFER = 3;
}
