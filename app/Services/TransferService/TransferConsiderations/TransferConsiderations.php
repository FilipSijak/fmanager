<?php

namespace App\Services\TransferService\TransferConsiderations;

use App\Events\Transfers\TransferEvent;
use App\Events\Transfers\TransferEventType;
use App\Models\Transfer;
use App\Repositories\TransferRepository;
use App\Services\TransferService\TransferStatusTypes;

class TransferConsiderations
{
    private PlayerConsideration $playerConsideration;
    private ClubConsideration $clubConsideration;
    private TransferRepository  $transferRepository;

    public function __construct(
        PlayerConsideration $playerConsideration,
        ClubConsideration $clubConsideration,
        TransferRepository $transferRepository
    )
    {
        $this->playerConsideration = $playerConsideration;
        $this->clubConsideration = $clubConsideration;
        $this->transferRepository = $transferRepository;
    }

    public function playerDecision(Transfer $transfer): void
    {
        $playerDecision = $this->playerConsideration->considerOffer($transfer);

        if ($playerDecision->counterOffer) {
            $playerUpdateDecision = TransferStatusTypes::PLAYER_COUNTEROFFER->value;
        } elseif (!$playerDecision->acceptableTransfer) {
            $playerUpdateDecision = TransferStatusTypes::PLAYER_DECLINED->value;
        } else {
            $playerUpdateDecision = TransferStatusTypes::WAITING_PAPERWORK->value;
        }

        $this->transferRepository->updateTransferStatus($transfer, $playerUpdateDecision);

        match ($playerUpdateDecision) {
            TransferStatusTypes::PLAYER_COUNTEROFFER->value => event(new TransferEvent(TransferEventType::PlayerCountered, $transfer->fresh())),
            TransferStatusTypes::WAITING_PAPERWORK->value => event(new TransferEvent(TransferEventType::PlayerAccepted, $transfer->fresh())),
            default => null,
        };
    }

    public function sellingClubDecision(Transfer $transfer): bool
    {
        $decision = $this->clubConsideration->considerOffer($transfer);

        if (!$decision->getAcceptableTransfer()) {

            if ($decision->getCounterOffer()) {

                $transferFinancialDetails = $transfer->transferFinancialDetails()->first();

                $transferFinancialDetails->amount = $decision->getCounterOffer();
                $transferFinancialDetails->save();

                $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::TARGET_CLUB_COUNTEROFFER->value);

                event(new TransferEvent(TransferEventType::SellingClubCountered, $transfer->fresh()));

                return false;
            }

            $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::TARGET_CLUB_DECLINED->value);

            return false;
        }

        return true;
    }

    public function playerCounterOffer(Transfer $transfer): void
    {
        $decision = $this->clubConsideration->considerPlayerContractCounterOffer($transfer);

        if ($decision) {
            $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::WAITING_PAPERWORK->value);

            event(new TransferEvent(TransferEventType::PlayerCounterofferAccepted, $transfer->fresh()));

            return;
        }

        $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::TRANSFER_FAILED->value);

        event(new TransferEvent(TransferEventType::PlayerCounterofferRejected, $transfer->fresh()));
    }
}
