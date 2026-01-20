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

    public function playerDecision(Transfer $transfer): int
    {
        $playerDecision = $this->playerConsideration->considerOffer($transfer);

        $this->transferRepository->updateTransferStatus($transfer, $playerDecision);

        return $playerDecision;
    }

    public function sellingClubDecision(Transfer $transfer): bool
    {
        $decision = $this->clubConsideration->considerOffer($transfer);

        if (!$decision->getAcceptableTransfer()) {

            if ($decision->getCounterOffer()) {

                $transferFinancialDetails = $transfer->transferFinancialDetails()->first();

                $transferFinancialDetails->amount = $decision->getCounterOffer();
                $transferFinancialDetails->save();

                $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::TARGET_CLUB_COUNTEROFFER);

                return false;
            }

            $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::TARGET_CLUB_DECLINED);

            return false;
        }

        $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::WAITING_PLAYER);

        return true;
    }

    public function waitingPaperwork(Transfer $transfer)
    {
        $medical = $this->transferRepository->processMedical($transfer);

        if (!$medical) {
            $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::TRANSFER_FAILED);
            // update news feed for medical @todo
            return 0;
        }

        $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::MOVE_PLAYER);
    }

    public function targetClubCounterOffer(Transfer $transfer)
    {

    }

    public function transferPlayer(Transfer $transfer)
    {
        $this->transferRepository->transferPlayerToNewClub($transfer);

        $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::TRANSFER_COMPLETED);

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
