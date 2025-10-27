<?php

namespace App\Services\TransferService;

// relates to transfer_status table
abstract class TransferStatusTypes
{
    const WAITING_TARGET_CLUB = 1;
    const WAITING_PLAYER = 2;
    const WAITING_PAPERWORK = 3;
    const WAITING_TRANSFER_WINDOW = 4;
    const MOVE_PLAYER = 5;
    const SOURCE_CLUB_COUNTEROFFER = 6;
    const TARGET_CLUB_COUNTEROFFER = 7;
    const COUNTEROFFER_ACCEPTED = 8;
    const PLAYER_COUNTEROFFER = 9;
    const PLAYER_COUNTEROFFER_ACCEPTED = 10;
    const SOURCE_CLUB_PLAYER_COUNTEROFFER = 11;
    const PLAYER_DECLINED = 12;
    const TARGET_CLUB_DECLINED = 13;
    const TRANSFER_COMPLETED = 14;
    const TRANSFER_FAILED = 15;
}
