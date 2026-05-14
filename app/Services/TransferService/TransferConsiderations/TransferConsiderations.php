<?php

namespace App\Services\TransferService\TransferConsiderations;

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

    public function setSeasonId(int $seasonId): void
    {
        $this->transferRepository->setSeasonId($seasonId);
    }

    public function setInstanceId(int $instanceId): void
    {
        $this->transferRepository->setInstanceId($instanceId);
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
    }

    public function sellingClubDecision(Transfer $transfer): void
    {
        $decision = $this->clubConsideration->considerOffer($transfer);

        if (!$decision->getAcceptableTransfer()) {

            if ($decision->getCounterOffer()) {

                $transferFinancialDetails = $transfer->transferFinancialDetails()->first();

                $transferFinancialDetails->amount = $decision->getCounterOffer();
                $transferFinancialDetails->save();

                $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::TARGET_CLUB_COUNTEROFFER->value);

                return;
            }

            $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::TARGET_CLUB_DECLINED->value);

            return;
        }

        $this->transferRepository->makePlayerContractOffer($transfer);
    }

    public function waitingPaperwork(Transfer $transfer): void
    {
        $medical = $this->transferRepository->processMedical($transfer);

        if (!$medical) {
            $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::TRANSFER_FAILED->value);

            return;
        }

        $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::MOVE_PLAYER->value);
    }

    public function targetClubCounterOffer(Transfer $transfer)
    {

    }

    public function transferPlayer(Transfer $transfer)
    {
        $this->transferRepository->transferPlayerToNewClub($transfer);

        $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::TRANSFER_COMPLETED->value);

        /*
         * @todo
         * move player to a new club
         * copy contract from the offer and update his current contract with it
         * remove contract offer
         * start financial transfer between club if it's not a free transfer
         * - deduct player signing fee from source club balance
         * - deduct agent fee from source club balance
         * - setup installments
         * - reset player happiness
         * set transfer status to TRANSFER_COMPLETED
         */

    }

    public function cancelOrRenegotiateTransfer(Transfer $transfer)
    {
        echo 'cancelOrRenegotiateTransfer';
    }

    public function targetClubConsideration($transfer)
    {
        echo 'targetClubConsideration';
    }
}
