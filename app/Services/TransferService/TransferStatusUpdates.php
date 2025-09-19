<?php

namespace App\Services\TransferService;

use App\Models\Club;
use App\Models\Transfer;
use App\Repositories\TransferRepository;
use App\Services\TransferService\TransferConsiderations\TransferConsiderations;

/**
 * SOURCE_CLUB - club that made an offer
 * TARGET_CLUB - club that owns the player
 */
class TransferStatusUpdates
{
    private TransferConsiderations $transferConsiderations;
    private TransferRepository     $transferRepository;

    public function __construct(
        TransferConsiderations $transferConsiderations,
        TransferRepository $transferRepository)
    {
        $this->transferConsiderations = $transferConsiderations;
        $this->transferRepository = $transferRepository;
    }

    private array $freeTransferActions = [
        TransferStatusTypes::WAITING_PLAYER => 'playerConsideration',
        TransferStatusTypes::PLAYER_COUNTEROFFER => 'playerCounterOffer',
        TransferStatusTypes::WAITING_PAPERWORK => 'waitingPaperwork',
        TransferStatusTypes::PLAYER_DECLINED => 'cancelOrRenegotiateTransfer',
        TransferStatusTypes::MOVE_PLAYER => 'transferPlayer',
    ];

    public function freeTransferUpdates(Transfer $transfer): void
    {
        call_user_func([$this->transferConsiderations, $this->freeTransferActions[$transfer->source_club_status]], $transfer);
    }

    public function loanTransferUpdates(Transfer $transfer): void
    {
        switch ($transfer->source_club_status) {
            case TransferStatusTypes::WAITING_TARGET_CLUB:
                break;
            case TransferStatusTypes::WAITING_PLAYER:
                $this->transferConsiderations->playerDecision($transfer);
            case TransferStatusTypes::WAITING_PAPERWORK:
                break;
            case TransferStatusTypes::MOVE_PLAYER:
                $this->transferRepository->transferPlayerToNewClub($transfer);
                break;
        }
    }

    public function permanentTransferUpdates(Transfer $transfer): void
    {
        switch ($transfer->source_club_status) {
            case TransferStatusTypes::WAITING_TARGET_CLUB:
                $this->transferConsiderations->sellingClubDecision($transfer);
                break;
            case TransferStatusTypes::WAITING_PLAYER:
                $this->transferConsiderations->playerDecision($transfer);
                break;
            case TransferStatusTypes::WAITING_PAPERWORK:
                $this->transferConsiderations->waitingPaperwork($transfer);
                break;
            case TransferStatusTypes::WAITING_TRANSFER_WINDOW:
                // check if transfer window started and move player if so
                $this->transferRepository->transferPlayerToNewClub($transfer);
                break;
            case TransferStatusTypes::MOVE_PLAYER:
                $this->transferRepository->transferPlayerToNewClub($transfer);
                break;
            case TransferStatusTypes::SOURCE_CLUB_COUNTEROFFER:
                // SOURCE_CLUB_COUNTEROFFER
            case TransferStatusTypes::TARGET_CLUB_COUNTEROFFER:
                // TARGET_CLUB_COUNTEROFFER
            case TransferStatusTypes::TARGET_CLUB_COUNTEROFFER_ACCEPTED:
                // TARGET_CLUB_COUNTEROFFER_ACCEPTED
            case TransferStatusTypes::PLAYER_COUNTEROFFER:
                // TARGET_CLUB_COUNTEROFFER_ACCEPTED
            case TransferStatusTypes::PLAYER_COUNTEROFFER_ACCEPTED:
                // TARGET_CLUB_COUNTEROFFER_ACCEPTED
            case TransferStatusTypes::SOURCE_CLUB_PLAYER_COUNTEROFFER:
                // TARGET_CLUB_COUNTEROFFER_ACCEPTED
            case TransferStatusTypes::PLAYER_DECLINED:
                // TARGET_CLUB_COUNTEROFFER_ACCEPTED
            case TransferStatusTypes::TARGET_CLUB_DECLINED:
                // TARGET_CLUB_COUNTEROFFER_ACCEPTED
            case TransferStatusTypes::TRANSFER_COMPLETED:
                //move player to source club and complete transfer
                $this->transferRepository->transferPlayerToNewClub($transfer);
                break;
            case TransferStatusTypes::TRANSFER_FAILED:
                $this->transferRepository->removeTransfersAndOffers($transfer);
                break;
        }
    }
}
