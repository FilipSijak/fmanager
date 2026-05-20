<?php

namespace App\Services\TransferService;

use App\Models\Instance;
use App\Models\Transfer;
use App\Services\TransferService\TransferWindowConfig\TransferWindowAvailability;

/**
 * SOURCE_CLUB - club that made an offer
 * TARGET_CLUB - club that owns the player
 */
class TransferStatusUpdates
{
    private TransferWorkflow $transferWorkflow;

    public function __construct(
        TransferWorkflow $transferWorkflow
    )
    {
        $this->transferWorkflow = $transferWorkflow;
    }

    private array $freeTransferActions = [
        TransferStatusTypes::WAITING_PLAYER->value => 'playerDecision',
        TransferStatusTypes::PLAYER_COUNTEROFFER->value => 'playerCounterOffer',
        TransferStatusTypes::WAITING_PAPERWORK->value => 'waitingPaperwork',
        TransferStatusTypes::PLAYER_DECLINED->value => 'playerDeclined',
        TransferStatusTypes::MOVE_PLAYER->value => 'transferPlayerToNewClub',
    ];

    public function freeTransferUpdates(Transfer $transfer): void
    {
        call_user_func([$this->transferWorkflow, $this->freeTransferActions[$transfer->transfer_status]], $transfer);
    }

    public function loanTransferUpdates(Transfer $transfer): void
    {
        switch ($transfer->transfer_status) {
            case TransferStatusTypes::WAITING_TARGET_CLUB->value:
                break;
            case TransferStatusTypes::WAITING_PLAYER->value:
                $this->transferWorkflow->playerDecision($transfer);
                break;
            case TransferStatusTypes::WAITING_PAPERWORK->value:
                $this->transferWorkflow->waitingPaperwork($transfer);
                break;
            case TransferStatusTypes::MOVE_PLAYER->value:
                $this->transferWorkflow->transferPlayerToNewClub($transfer);
                break;
        }
    }

    public function permanentTransferUpdates(Transfer $transfer): void
    {
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
            TransferStatusTypes::WAITING_TARGET_CLUB => $this->transferWorkflow->sellingClubDecision($transfer),
            TransferStatusTypes::WAITING_PLAYER => $this->transferWorkflow->playerDecision($transfer),
            TransferStatusTypes::WAITING_PAPERWORK => $this->transferWorkflow->waitingPaperwork($transfer),
            TransferStatusTypes::WAITING_TRANSFER_WINDOW => $this->handleWaitingTransferWindow($transfer),
            TransferStatusTypes::MOVE_PLAYER => $this->transferWorkflow->transferPlayerToNewClub($transfer),
        };
    }

    private function handlePermanentNegotiationStatus(Transfer $transfer): void
    {
        match (TransferStatusTypes::from($transfer->transfer_status)) {
            TransferStatusTypes::TARGET_CLUB_COUNTEROFFER => $this->transferWorkflow->transferFeeCounterOffer($transfer),
            TransferStatusTypes::COUNTEROFFER_ACCEPTED => $this->transferWorkflow->makePlayerContractOffer($transfer),
            TransferStatusTypes::PLAYER_COUNTEROFFER => $this->transferWorkflow->playerCounterOffer($transfer),
        };
    }

    private function handlePermanentTerminalStatus(Transfer $transfer): void
    {
        match (TransferStatusTypes::from($transfer->transfer_status)) {
            TransferStatusTypes::PLAYER_DECLINED,
            TransferStatusTypes::TARGET_CLUB_DECLINED => $this->transferWorkflow->targetClubDeclined($transfer),
            TransferStatusTypes::TRANSFER_COMPLETED => $this->transferWorkflow->removeTransferContractOffer($transfer),
            TransferStatusTypes::TRANSFER_FAILED => $this->transferWorkflow->removeTransferAndPlayerOffers($transfer),
        };
    }

    private function handleWaitingTransferWindow(Transfer $transfer): void
    {
        $instance = Instance::findOrFail($transfer->instance_id);

        if (TransferWindowAvailability::isTransferWindowOpen($instance->instance_date)) {
            $this->transferWorkflow->transferPlayerToNewClub($transfer);
        }
    }
}
