<?php

namespace App\DataModels;

class PlayerContractDecision
{
    public bool $acceptableTransfer = false {
        get {
            return $this->acceptableTransfer;
        }
        set {
            $this->acceptableTransfer = $value;
        }
    }
    public int $counterOffer = 0 {
        get {
            return $this->counterOffer;
        }
        set {
            $this->counterOffer = $value;
        }
    }
}
