<?php

namespace App\Services\TransferService;

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
        TransferStatusTypes::WAITING_PLAYER->value => 'playerConsideration',
        TransferStatusTypes::PLAYER_COUNTEROFFER->value => 'playerCounterOffer',
        TransferStatusTypes::WAITING_PAPERWORK->value => 'waitingPaperwork',
        TransferStatusTypes::PLAYER_DECLINED->value => 'cancelOrRenegotiateTransfer',
        TransferStatusTypes::MOVE_PLAYER->value => 'transferPlayer',
        TransferStatusTypes::TARGET_CLUB_COUNTEROFFER->value => 'targetClubCounterOffer',
    ];

    public function freeTransferUpdates(Transfer $transfer): void
    {
        call_user_func([$this->transferConsiderations, $this->freeTransferActions[$transfer->transfer_status]], $transfer);
    }

    public function loanTransferUpdates(Transfer $transfer): void
    {
        switch ($transfer->transfer_status) {
            case TransferStatusTypes::WAITING_TARGET_CLUB->value:
                break;
            case TransferStatusTypes::WAITING_PLAYER->value:
                $this->transferConsiderations->playerDecision($transfer);
            case TransferStatusTypes::WAITING_PAPERWORK->value:
                break;
            case TransferStatusTypes::MOVE_PLAYER->value:
                $this->transferRepository->transferPlayerToNewClub($transfer);
                break;
        }
    }

    public function permanentTransferUpdates(Transfer $transfer): void
    {
        //TARGET - selling club
        //SOURCE - offering club

        switch ($transfer->transfer_status) {
            case TransferStatusTypes::WAITING_TARGET_CLUB->value:
                $this->transferConsiderations->sellingClubDecision($transfer);
                break;
            case TransferStatusTypes::WAITING_PLAYER->value:
                $this->transferConsiderations->playerDecision($transfer);
                break;
            case TransferStatusTypes::WAITING_PAPERWORK->value:
                $this->transferConsiderations->waitingPaperwork($transfer);
                break;
            case TransferStatusTypes::WAITING_TRANSFER_WINDOW->value:
                // check if transfer window started and move player if so
                $this->transferRepository->transferPlayerToNewClub($transfer);
                break;
            case TransferStatusTypes::MOVE_PLAYER->value:
                $this->transferRepository->transferPlayerToNewClub($transfer);
                break;
            case TransferStatusTypes::SOURCE_CLUB_COUNTEROFFER->value:
                $this->transferRepository->transferFeeCounterOffer($transfer, TransferStatusTypes::SOURCE_CLUB_COUNTEROFFER->value);
                break;
            case TransferStatusTypes::TARGET_CLUB_COUNTEROFFER->value:
                $this->transferRepository->transferFeeCounterOffer($transfer, TransferStatusTypes::TARGET_CLUB_COUNTEROFFER->value);
                break;
            case TransferStatusTypes::COUNTEROFFER_ACCEPTED->value:
                $this->transferRepository->makePlayerContractOffer($transfer);
                break;
            case TransferStatusTypes::PLAYER_COUNTEROFFER->value:
                // implement
                break;
            case TransferStatusTypes::PLAYER_COUNTEROFFER_ACCEPTED->value:
                $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::MOVE_PLAYER->value);
                break;
            case TransferStatusTypes::SOURCE_CLUB_PLAYER_COUNTEROFFER->value:
                // player reconsider
                $this->transferConsiderations->playerDecision($transfer);
                break;
            case TransferStatusTypes::PLAYER_DECLINED->value:
                // update news feed with player declined
                // $this->updateTransferStatus($transfer,TransferStatusTypes::TRANSFER_FAILED);
            case TransferStatusTypes::TARGET_CLUB_DECLINED->value:
                // update news feed with target club declined
            case TransferStatusTypes::TRANSFER_COMPLETED->value:
                // update news feed with target club declined
            case TransferStatusTypes::TRANSFER_FAILED->value:
                $this->transferRepository->removeTransferAndPlayerOffers($transfer);
                break;
        }
    }
}
