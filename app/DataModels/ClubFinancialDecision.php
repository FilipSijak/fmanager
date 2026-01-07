<?php

namespace App\DataModels;

class ClubFinancialDecision
{
    private bool $lowOffer = false;
    private int $counterOffer = 0;
    private bool $acceptableTransfer = false;

    public function isLowOffer(): bool
    {
        return $this->lowOffer;
    }

    public function setLowOffer(bool $lowOffer): void
    {
        $this->lowOffer = $lowOffer;
    }

    public function getCounterOffer(): int
    {
        return $this->counterOffer;
    }

    public function setCounterOffer(int $counterOffer): void
    {
        $this->counterOffer = $counterOffer;
    }

    public function isAcceptableTransfer(): bool
    {
        return $this->acceptableTransfer;
    }

    public function setAcceptableTransfer(bool $acceptableTransfer): void
    {
        $this->acceptableTransfer = $acceptableTransfer;
    }
}
