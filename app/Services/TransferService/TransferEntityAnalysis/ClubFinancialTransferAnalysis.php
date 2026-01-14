<?php

namespace App\Services\TransferService\TransferEntityAnalysis;

use App\DataModels\ClubFinancialDecision;
use App\DataModels\PlayerImportance;
use App\Models\Account;
use App\Models\Player;
use App\Models\Transfer;
use App\Models\TransferFinancialDetails;

class ClubFinancialTransferAnalysis
{
    public function isFinanciallyAcceptableTransfer(Transfer $transfer, PlayerImportance $playerImportance): ClubFinancialDecision
    {
        $sellingClubAccounts = Account::where('club_id', $transfer->target_club_id)->first();
        $player = Player::find($transfer->player_id);
        $clubFinancialDecision = $this->setDefaultDecision();
        $transferFinancialDetail = TransferFinancialDetails::where('transfer_id', $transfer->id)->first();

        $keyPlayerMinOfferValue = $player->value * 1.2;
        $maxKeyPlayerValue = $player->value * 1.3;
        $bestInPositionMinOfferValue = $player->value * 1.1;
        $maxBestInPositionOfferValue = $player->value * 1.15;

        $urgentSaleDecision = $this->urgentSaleDecision($sellingClubAccounts, $transferFinancialDetail, $player);

        if ($urgentSaleDecision->isAcceptableTransfer()) {
            return $urgentSaleDecision;
        }

        if ($playerImportance->isKeyPlayer()) {
            return $this->importantPlayersFinanceDecision($transferFinancialDetail, $keyPlayerMinOfferValue, $maxKeyPlayerValue);
        }

        if ($playerImportance->isBestInPosition() || $playerImportance->isPositionDeficit()) {
            return $this->importantPlayersFinanceDecision($transferFinancialDetail, $bestInPositionMinOfferValue, $maxBestInPositionOfferValue);
        }

        return $clubFinancialDecision;
    }

    private function urgentSaleDecision(
        Account $sellingClubAccounts,
        TransferFinancialDetails $transferFinancialDetails,
        Player $player
    ): ClubFinancialDecision
    {
        $urgentSaleValue = $player->value * 0.9;
        $clubFinancialDecision = $this->setDefaultDecision();

        if ($sellingClubAccounts->balance < 0 && $sellingClubAccounts->future_balance < 0 && $transferFinancialDetails->amount >= $urgentSaleValue) {
            $clubFinancialDecision->setAcceptableTransfer(true);

            return $clubFinancialDecision;
        }

        $clubFinancialDecision->setLowOffer(true);

        return $clubFinancialDecision;
    }

    private function importantPlayersFinanceDecision(
        TransferFinancialDetails $transferFinancialDetail,
        int $minValue,
        int $maxValue
    ): ClubFinancialDecision
    {
        $clubFinancialDecision = $this->setDefaultDecision();

        if ($transferFinancialDetail->amount >= $minValue) {
            if ($transferFinancialDetail->amount < $maxValue) {
                $clubFinancialDecision->setCounterOffer($maxValue);

                return $clubFinancialDecision;
            }

            $clubFinancialDecision->setCounterOffer(0);
            $clubFinancialDecision->setAcceptableTransfer(true);

            return $clubFinancialDecision;
        }

        $clubFinancialDecision->setLowOffer(true);

        return $clubFinancialDecision;
    }

    private function setDefaultDecision(): ClubFinancialDecision
    {
        $clubFinancialDecision = new ClubFinancialDecision();
        $clubFinancialDecision->setLowOffer(false);
        $clubFinancialDecision->setCounterOffer(0);
        $clubFinancialDecision->setAcceptableTransfer(false);

        return $clubFinancialDecision;
    }
}
