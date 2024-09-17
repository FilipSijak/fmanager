<?php

namespace App\Services\TransferService;

use App\Models\Club;
use App\Models\Transfer;
use App\Services\TransferService\TransferConsiderations\TransferConsiderations;

/**
 * SOURCE_CLUB - club that made an offer
 * TARGET_CLUB - club that owns the player
 */
class TransferStatusUpdates
{
    private TransferConsiderations $transferConsiderations;

    public function __construct(TransferConsiderations $transferConsiderations)
    {
        $this->transferConsiderations = $transferConsiderations;
    }

    private array $freeTransferActions = [
        TransferStatusTypes::WAITING_PLAYER => 'playerConsideration',
        TransferStatusTypes::PLAYER_COUNTEROFFER => 'playerCounterOffer',
        TransferStatusTypes::WAITING_PAPERWORK => 'waitingPaperwork',
        TransferStatusTypes::PLAYER_DECLINED => 'cancelOrRenegotiateTransfer',

    ];

    public function freeTransferUpdates(Transfer $transfer): void
    {
        call_user_func([$this->transferConsiderations, $this->freeTransferActions[$transfer->source_club_status]], $transfer);
    }

    public function loanTransferUpdates(Transfer $transfer): void
    {
        switch ($transfer->source_club_status) {
            case TransferStatusTypes::WAITING_TARGET_CLUB:
                // target club needs to consider the offer
                break;
            case TransferStatusTypes::WAITING_PLAYER:
                $this->transferConsiderations->playerConsideration($transfer);
            case TransferStatusTypes::PLAYER_APPROVED:
                // request paperwork
            case TransferStatusTypes::WAITING_PAPERWORK:
                // if medical passed, finish transfer
        }
    }

    public function permanentTransferUpdates(Transfer $transfer): void
    {
        switch ($transfer->source_club_status) {
            case TransferStatusTypes::WAITING_TARGET_CLUB:
                // target club needs to consider the offer
                break;
            case TransferStatusTypes::WAITING_PLAYER:
                $this->transferConsiderations->playerConsideration($transfer);
            case TransferStatusTypes::PLAYER_APPROVED:
                // request paperwork
            case TransferStatusTypes::WAITING_PAPERWORK:
            $this->transferConsiderations->waitingPaperwork($transfer);
            case TransferStatusTypes::TARGET_CLUB_COUNTEROFFER:
                // reconsider counteroffer, cancel transfer if needed
            case TransferStatusTypes::TARGET_CLUB_DECLINED:
                // SOURCE_CLUB_COUNTEROFFER or cancel the deal
            case TransferStatusTypes::PLAYER_DECLINED:
                // improve player offer or cancel the deal
        }
    }
}
