<?php

namespace App\Services\TransferService;

enum TransferType: int
{
    case FREE_TRANSFER = 3;
    case LOAN_TRANSFER = 2;
    case PERMANENT_TRANSFER = 1;
}
