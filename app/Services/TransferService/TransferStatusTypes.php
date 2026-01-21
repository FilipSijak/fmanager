<?php

namespace App\Services\TransferService;

// relates to transfer_status table
enum TransferStatusTypes: int
{
    case WAITING_TARGET_CLUB = 1;
    case WAITING_PLAYER = 2;
    case WAITING_PAPERWORK = 3;
    case WAITING_TRANSFER_WINDOW = 4;
    case MOVE_PLAYER = 5;
    case SOURCE_CLUB_COUNTEROFFER = 6;
    case TARGET_CLUB_COUNTEROFFER = 7;
    case COUNTEROFFER_ACCEPTED = 8;
    case PLAYER_COUNTEROFFER = 9;
    case PLAYER_COUNTEROFFER_ACCEPTED = 10;
    case SOURCE_CLUB_PLAYER_COUNTEROFFER = 11;
    case PLAYER_DECLINED = 12;
    case TARGET_CLUB_DECLINED = 13;
    case TRANSFER_COMPLETED = 14;
    case TRANSFER_FAILED = 15;

    const array TRANSFER_STATUS_TYPES_LIST = [
        self::WAITING_TARGET_CLUB,
        self::WAITING_PLAYER,
        self::WAITING_PAPERWORK,
        self::WAITING_TRANSFER_WINDOW,
        self::MOVE_PLAYER,
        self::SOURCE_CLUB_COUNTEROFFER,
        self::TARGET_CLUB_COUNTEROFFER,
        self::COUNTEROFFER_ACCEPTED,
        self::PLAYER_COUNTEROFFER,
        self::PLAYER_COUNTEROFFER_ACCEPTED,
        self::SOURCE_CLUB_PLAYER_COUNTEROFFER,
        self::PLAYER_DECLINED,
        self::TARGET_CLUB_DECLINED,
        self::TRANSFER_COMPLETED,
        self::TRANSFER_FAILED,
    ];

}
