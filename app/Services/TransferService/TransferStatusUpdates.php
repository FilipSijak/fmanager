<?php

namespace App\Services\TransferService;

use App\Models\Instance;
use App\Models\Transfer;
use App\Repositories\TransferRepository;
use App\Services\TransferService\TransferConsiderations\TransferConsiderations;
use App\Services\TransferService\TransferWindowConfig\TransferWindowAvailability;

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
                break;
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

        match (TransferStatusTypes::from($transfer->transfer_status)) {
            TransferStatusTypes::WAITING_TARGET_CLUB,
            TransferStatusTypes::WAITING_PLAYER,
            TransferStatusTypes::WAITING_PAPERWORK,
            TransferStatusTypes::WAITING_TRANSFER_WINDOW,
            TransferStatusTypes::MOVE_PLAYER => $this->handlePermanentProgressionStatus($transfer),

            TransferStatusTypes::TARGET_CLUB_COUNTEROFFER,
            TransferStatusTypes::COUNTEROFFER_ACCEPTED,
            TransferStatusTypes::PLAYER_COUNTEROFFER => $this->handlePermanentNegotiationStatus($transfer),

            TransferStatusTypes::PLAYER_DECLINED,
            TransferStatusTypes::TARGET_CLUB_DECLINED,
            TransferStatusTypes::TRANSFER_COMPLETED,
            TransferStatusTypes::TRANSFER_FAILED => $this->handlePermanentTerminalStatus($transfer),

            default => null,
        };
    }

    private function handlePermanentProgressionStatus(Transfer $transfer): void
    {
        match (TransferStatusTypes::from($transfer->transfer_status)) {
            TransferStatusTypes::WAITING_TARGET_CLUB => $this->transferConsiderations->sellingClubDecision($transfer),
            TransferStatusTypes::WAITING_PLAYER => $this->transferConsiderations->playerDecision($transfer),
            TransferStatusTypes::WAITING_PAPERWORK => $this->transferConsiderations->waitingPaperwork($transfer),
            TransferStatusTypes::WAITING_TRANSFER_WINDOW => $this->handleWaitingTransferWindow($transfer),
            TransferStatusTypes::MOVE_PLAYER => $this->transferRepository->transferPlayerToNewClub($transfer),
        };
    }

    private function handlePermanentNegotiationStatus(Transfer $transfer): void
    {
        match (TransferStatusTypes::from($transfer->transfer_status)) {
            TransferStatusTypes::TARGET_CLUB_COUNTEROFFER => $this->transferRepository->transferFeeCounterOffer($transfer),
            TransferStatusTypes::COUNTEROFFER_ACCEPTED => $this->transferRepository->makePlayerContractOffer($transfer),
            TransferStatusTypes::PLAYER_COUNTEROFFER => $this->transferConsiderations->playerCounterOffer($transfer),
        };
    }

    private function handlePermanentTerminalStatus(Transfer $transfer): void
    {
        match (TransferStatusTypes::from($transfer->transfer_status)) {
            TransferStatusTypes::PLAYER_DECLINED,
            TransferStatusTypes::TARGET_CLUB_DECLINED => $this->transferRepository->updateTransferStatus(
                $transfer,
                TransferStatusTypes::TRANSFER_FAILED->value
            ),
            TransferStatusTypes::TRANSFER_COMPLETED => $this->transferRepository->removeTransferContractOffer($transfer),
            TransferStatusTypes::TRANSFER_FAILED => $this->transferRepository->removeTransferAndPlayerOffers($transfer),
        };
    }

    private function handleWaitingTransferWindow(Transfer $transfer): void
    {
        $instance = Instance::findOrFail($transfer->instance_id);

        if (TransferWindowAvailability::isTransferWindowOpen($instance->instance_date)) {
            $this->transferRepository->transferPlayerToNewClub($transfer);
        }
    }
}
