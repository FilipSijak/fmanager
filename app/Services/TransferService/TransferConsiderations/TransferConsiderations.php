<?php

namespace App\Services\TransferService\TransferConsiderations;

use App\Models\Transfer;

class TransferConsiderations
{
    private PlayerConsideration $playerConsideration;

    public function __construct(
        PlayerConsideration $playerConsideration
    )
    {
        $this->playerConsideration = $playerConsideration;
    }

    public function playerConsideration(Transfer $transfer)
    {
        $this->playerConsideration->considerOffer($transfer);

        echo 'playerConsideration';
    }

    public function playerCounterOffer(Transfer $transfer)
    {
        echo 'playerCounterOffer';
    }

    public function requestPaperwork(Transfer $transfer)
    {
        // both player and club status updates to waiting for paperwork
        echo ' requestPaperwork';
    }

    public function waitingPaperwork(Transfer $transfer)
    {
        // do medical and complete or cancel transfer
        echo 'waitingPaperwork';
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
