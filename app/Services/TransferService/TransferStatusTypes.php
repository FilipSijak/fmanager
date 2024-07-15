<?php

namespace App\Services\TransferService;

// relates to transfer_status table
enum TransferStatusTypes: int
{
    case WAITING_TARGET_CLUB = 1;
    case WAITING_SOURCE_CLUB = 2;
    case WAITING_PLAYER = 3;
    case WAITING_PAPERWORK = 4;
    case WAITING_TRANSFER_WINDOW = 5;
    case TRANSFER_COMPLETED = 6;
    case TRANSFER_FAILED = 7;
}
