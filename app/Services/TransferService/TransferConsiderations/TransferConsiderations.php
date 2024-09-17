<?php

namespace App\Services\TransferService\TransferConsiderations;

use App\Models\Transfer;
use App\Repositories\TransferRepository;
use App\Services\TransferService\TransferStatusTypes;

class TransferConsiderations
{
    private PlayerConsideration $playerConsideration;
    private TransferRepository  $transferRepository;

    public function __construct(
        PlayerConsideration $playerConsideration,
        TransferRepository $transferRepository
    )
    {
        $this->playerConsideration = $playerConsideration;
        $this->transferRepository = $transferRepository;
    }

    public function playerConsideration(Transfer $transfer): int
    {
        $playerDecision = $this->playerConsideration->considerOffer($transfer);

        $this->transferRepository->updateTransferStatus($transfer, $playerDecision);

        return $playerDecision;
    }

    public function waitingPaperwork(Transfer $transfer)
    {
        // do medical and complete or cancel transfer
        $medical = $this->transferRepository->processMedical($transfer);

        dd($medical);
        // update news feed for medical @todo
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
