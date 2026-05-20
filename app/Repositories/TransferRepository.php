<?php

namespace App\Repositories;

use AllowDynamicProperties;
use App\Http\Requests\CreateTransferRequest;
use App\Http\Requests\FreeTransferRequest;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\Transfer;
use App\Models\TransferContractOffer;
use App\Models\TransferFinancialDetails;
use App\Services\FinanceService\FinanceService;
use App\Services\TransferService\TransferEntityAnalysis\PlayerValuation;
use App\Services\TransferService\TransferFinancialSettlement;
use App\Services\TransferService\TransferState;
use App\Services\TransferService\TransferStatusTypes;
use App\Services\TransferService\TransferTypes;
use App\Services\TransferService\TransferWindowConfig\TransferWindowAvailability;
use Illuminate\Support\Facades\DB;

class TransferRepository extends CoreRepository
{
    private TransferState $transferState;

    public function __construct(
        TransferState $transferState
    )
    {
        $this->transferState = $transferState;
    }

    public function createAutomaticTransfer(
        Player $player,
        Club $buyingClub,
        $transferType,
    ): Transfer
    {
        $transfer = new Transfer([
            'instance_id' => $this->instanceId(),
            'season_id' => $this->seasonId(),
            'source_club_id' => $buyingClub->id,
            'player_id' => $player->id,
            'offer_date' => Instance::find($this->instanceId())->instance_date,
            'transfer_type' => $transferType,
        ]);

        $transfer->save();

        return $transfer;
    }

    public function createTransferFinancialDetails(
        Transfer $transfer,
        Player $player,
        Club $buyingClub,
        bool $urgentTransfer,
    ): TransferFinancialDetails
    {
        $transferFinancialDetails = TransferFinancialDetails::create([
            'amount' => PlayerValuation::buyingClubValuation($player, $buyingClub, $urgentTransfer),
            'transfer_id' => $transfer->id,
            'installments' => $this->setTransferInstallments($transfer, $buyingClub),
        ]);

        $transferFinancialDetails->save();

        return $transferFinancialDetails;
    }

    public function storeTransfer(CreateTransferRequest $request): Transfer
    {
        $transfer = new Transfer;
        $transferFinancialDetails = new TransferFinancialDetails;

        $transfer->instance_id = $this->instanceId();
        $transfer->season_id = $this->seasonId();
        $transfer->source_club_id = $request->input('source_club_id');
        $transfer->target_club_id = $request->input('target_club_id');
        $transfer->player_id = $request->input('player_id');
        $transfer->transfer_type = $request->input('transfer_type');
        // setting status for target club to respond after the initial offer
        $transfer->transfer_status = TransferStatusTypes::WAITING_TARGET_CLUB->value;

        $transfer->save();

        $transferFinancialDetails->transfer_id = $transfer->id;
        $transferFinancialDetails->amount = $request->input('amount');
        $transferFinancialDetails->installments = $request->input('installments');

        $transferFinancialDetails->save();

        return $transfer;
    }

    public function storeFreeTransfer(FreeTransferRequest $request): Transfer
    {
        $transfer = new Transfer;

        $transfer->instance_id = $this->instanceId();
        $transfer->season_id = $this->seasonId();
        $transfer->source_club_id = $request->input('source_club_id');
        $transfer->player_id = $request->input('player_id');
        $transfer->transfer_type = TransferTypes::FREE_TRANSFER;
        $transfer->offer_date = Instance::where('id', $this->instanceId())->first()->instance_date;
        $transfer->transfer_status = TransferStatusTypes::WAITING_PLAYER->value;

        $transfer->save();

        $contractOffer = new TransferContractOffer;

        $contractOffer->transfer_id = $transfer->id;
        $contractOffer->transfer_fee = $request->input('transfer_fee', 0);
        $contractOffer->salary = $request->input('salary');
        $contractOffer->appearance = $request->input('appearance');
        $contractOffer->assist = $request->input('assist');
        $contractOffer->goal = $request->input('goal');
        $contractOffer->league = $request->input('league');
        $contractOffer->pc_promotion_salary_raise = $request->input('pc_promotion_salary_raise');
        $contractOffer->pc_demotion_salary_cut = $request->input('pc_demotion_salary_cut');
        $contractOffer->cup = $request->input('cup');
        $contractOffer->el = $request->input('el');

        $contractOffer->save();

        return $transfer;
    }

    public function updateTransferStatus(Transfer $transfer, int $sourceClubStatus): Transfer
    {
        $this->transferState->transitionTo($transfer, TransferStatusTypes::from($sourceClubStatus));

        return $transfer;
    }

    public function removeTransferAndPlayerOffers(Transfer $transfer): void
    {
        $this->removeTransferContractOffer($transfer);
        $this->removeTransferFinancialDetails($transfer);
        $transfer->delete();
    }

    public function removeTransferContractOffer(Transfer $transfer): void
    {
        $contractOffer = $transfer->transferContractOffer()->first();

        $contractOffer?->delete();
    }

    public function removeTransferFinancialDetails(Transfer $transfer): void
    {
        $transferFinancialDetails = $transfer->transferFinancialDetails()->first();

        $transferFinancialDetails?->delete();
    }

    private function setTransferInstallments(Transfer $transfer, Club $club): int
    {
        $account = $club->account()->first();
        $transferFinancialDetails = $transfer->transferFinancialDetails()->first();

        if ($account->transfer_budget / 2 < $transferFinancialDetails->amount) {
            return 24;
        }

        return 0;
    }
}
