<?php

namespace App\Services\TransferService;

use App\Exceptions\InvalidTransferTransition;
use App\Models\Transfer;

final class TransferState
{
    private const ALLOWED = [
        TransferStatusTypes::WAITING_TARGET_CLUB->value => [
            TransferStatusTypes::WAITING_PLAYER->value,
            TransferStatusTypes::TARGET_CLUB_COUNTEROFFER->value,
            TransferStatusTypes::TARGET_CLUB_DECLINED->value,
        ],
        TransferStatusTypes::WAITING_PLAYER->value => [
            TransferStatusTypes::WAITING_PAPERWORK->value,
            TransferStatusTypes::PLAYER_COUNTEROFFER->value,
            TransferStatusTypes::PLAYER_DECLINED->value,
        ],
        TransferStatusTypes::WAITING_PAPERWORK->value => [
            TransferStatusTypes::MOVE_PLAYER->value,
            TransferStatusTypes::TRANSFER_FAILED->value,
        ],
        TransferStatusTypes::WAITING_TRANSFER_WINDOW->value => [
            TransferStatusTypes::TRANSFER_COMPLETED->value,
            TransferStatusTypes::TRANSFER_FAILED->value,
        ],
        TransferStatusTypes::MOVE_PLAYER->value => [
            TransferStatusTypes::WAITING_TRANSFER_WINDOW->value,
            TransferStatusTypes::TRANSFER_COMPLETED->value,
            TransferStatusTypes::TRANSFER_FAILED->value,
        ],
        TransferStatusTypes::SOURCE_CLUB_COUNTEROFFER->value => [
            TransferStatusTypes::TARGET_CLUB_COUNTEROFFER->value,
            TransferStatusTypes::TRANSFER_FAILED->value,
        ],
        TransferStatusTypes::TARGET_CLUB_COUNTEROFFER->value => [
            TransferStatusTypes::COUNTEROFFER_ACCEPTED->value,
            TransferStatusTypes::TRANSFER_FAILED->value,
        ],
        TransferStatusTypes::COUNTEROFFER_ACCEPTED->value => [
            TransferStatusTypes::WAITING_PLAYER->value,
        ],
        TransferStatusTypes::PLAYER_COUNTEROFFER->value => [
            TransferStatusTypes::WAITING_PAPERWORK->value,
            TransferStatusTypes::TRANSFER_FAILED->value,
        ],
        TransferStatusTypes::PLAYER_COUNTEROFFER_ACCEPTED->value => [
            TransferStatusTypes::MOVE_PLAYER->value,
        ],
        TransferStatusTypes::SOURCE_CLUB_PLAYER_COUNTEROFFER->value => [
            TransferStatusTypes::WAITING_PAPERWORK->value,
            TransferStatusTypes::PLAYER_DECLINED->value,
        ],
        TransferStatusTypes::PLAYER_DECLINED->value => [
            TransferStatusTypes::TRANSFER_FAILED->value,
        ],
        TransferStatusTypes::TARGET_CLUB_DECLINED->value => [
            TransferStatusTypes::TRANSFER_FAILED->value,
        ],
    ];

    public function transitionTo(Transfer $transfer, TransferStatusTypes $next): void
    {
        $current = (int) $transfer->transfer_status;

        if (!in_array($next->value, self::ALLOWED[$current] ?? [], true)) {
            throw new InvalidTransferTransition($current, $next->value);
        }

        $transfer->transfer_status = $next->value;
        $transfer->save();
    }
}
