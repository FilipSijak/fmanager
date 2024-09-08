<?php

namespace App\Services\TransferService;

// relates to transfer_status table
abstract class TransferStatusTypes
{
    const WAITING_TARGET_CLUB = 1;
    const WAITING_SOURCE_CLUB = 2;
    const WAITING_PLAYER = 3;
    const WAITING_PAPERWORK = 4;
    const WAITING_TRANSFER_WINDOW = 5;
    const TRANSFER_COMPLETED = 6;
    const TRANSFER_FAILED = 7;
    const TARGET_CLUB_APPROVED = 8;
    const SOURCE_CLUB_APPROVED = 9;
    const PLAYER_APPROVED = 10;
    const SOURCE_CLUB_COUNTEROFFER = 11;
    const TARGET_CLUB_COUNTEROFFER = 12;
    const PLAYER_COUNTEROFFER = 13;
    const PLAYER_DECLINED = 14;
    const SOURCE_CLUB_DECLINED = 15;
    const TARGET_CLUB_DECLINED = 16;
}
