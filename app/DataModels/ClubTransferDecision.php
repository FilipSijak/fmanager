<?php

namespace App\DataModels;

class ClubTransferDecision
{
    protected bool $acceptableTransfer = false;
    protected int $counterOffer = 0;

    public function getAcceptableTransfer(): bool
    {
        return $this->acceptableTransfer;
    }

    public function setAcceptableTransfer(bool $acceptableTransfer): void
    {
        $this->acceptableTransfer = $acceptableTransfer;
    }

    public function getCounterOffer(): int
    {
        return $this->counterOffer;
    }

    public function setCounterOffer(int $counterOffer): void
    {
        $this->counterOffer = $counterOffer;
    }
}
