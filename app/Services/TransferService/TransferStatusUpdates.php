<?php

namespace App\Services\TransferService;

use App\Models\Club;
use App\Models\Transfer;

/**
 * SOURCE_CLUB - club that made an offer
 * TARGET_CLUB - club that owns the player
 */
class TransferStatusUpdates
{
    private array $freeTransferActions = [
        TransferStatusTypes::WAITING_PLAYER => 'playerConsideration',
        TransferStatusTypes::PLAYER_COUNTEROFFER => 'playerCounterOffer',
        TransferStatusTypes::PLAYER_APPROVED => 'requestPaperwork',
        TransferStatusTypes::WAITING_PAPERWORK => 'waitingPaperwork',
        TransferStatusTypes::PLAYER_DECLINED => 'cancelOrRenegotiateTransfer',

    ];

    public function freeTransferUpdates(Transfer $transfer): void
    {
        foreach ($this->freeTransferActions as $status) {
            call_user_func([$this, $status], $transfer);
        }
    }

    public function loanTransferUpdates(Transfer $transfer): void
    {
        switch ($transfer->source_club_status) {
            case TransferStatusTypes::WAITING_TARGET_CLUB:
                // target club needs to consider the offer
                break;
            case TransferStatusTypes::WAITING_PLAYER:
                // target club accepted
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
                // target club accepted
            case TransferStatusTypes::PLAYER_APPROVED:
                // request paperwork
            case TransferStatusTypes::WAITING_PAPERWORK:
                // if medical passed, finish transfer
            case TransferStatusTypes::TARGET_CLUB_COUNTEROFFER:
                // reconsider counteroffer, cancel transfer if needed
            case TransferStatusTypes::TARGET_CLUB_DECLINED:
                // SOURCE_CLUB_COUNTEROFFER or cancel the deal
            case TransferStatusTypes::PLAYER_DECLINED:
                // improve player offer or cancel the deal
        }


        if ($transfer->transfer_status == TransferStatusTypes::WAITING_TARGET_CLUB) {
            $club = Club::where('id', $transfer->target_club_id)->first();

            if ($this->clubService->clubSellingDecision($transfer)) {
                // club approves, update status
            }
        }

        // if waiting for player approval
        if ($transfer->transfer_status == TransferStatusTypes::WAITING_PLAYER) {
            if ($this->personTransferService->isTransferAcceptable($transfer)) {
                // update transfer with person approved
            }
        }

        // counteroffer
        if ($transfer->transfer_status == TransferStatusTypes::WAITING_SOURCE_CLUB) {
            //is counteroffer acceptable
        }
    }

    private function playerConsideration(Transfer $transfer)
    {

    }

    private function playerCounterOffer(Transfer $transfer)
    {

    }

    private function requestPaperwork(Transfer $transfer)
    {
        // both player and club status updates to waiting for paperwork
    }

    private function waitingPaperwork(Transfer $transfer)
    {
        // do medical and complete or cancel transfer
    }

    private function cancelOrRenegotiateTransfer(Transfer $transfer)
    {

    }

    private function targetClubConsideration($transfer)
    {

    }
}
