<?php

namespace App\Repositories;

use App\Http\Requests\CreateTransferRequest;
use App\Http\Requests\FreeTransferRequest;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerInjury;
use App\Models\Transfer;
use App\Models\TransferContractOffer;
use App\Models\TransferFinancialDetails;
use App\Services\TransferService\TransferStatusTypes;
use App\Services\TransferService\TransferTypes;
use Illuminate\Support\Facades\DB;

class TransferRepository extends CoreRepository
{
    public function storeTransfer(CreateTransferRequest $request): Transfer
    {
        $transfer = new Transfer;
        $transferFinancialDetails = new TransferFinancialDetails;

        $transfer->season_id = $this->seasonId;
        $transfer->source_club_id = $request->input('source_club_id');
        $transfer->target_club_id = $request->input('target_club_id');
        $transfer->player_id = $request->input('player_id');
        $transfer->transfer_type = $request->input('transfer_type');
        // setting status for target club to respond after the initial offer
        $transfer->source_club_status = TransferStatusTypes::WAITING_TARGET_CLUB;

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

        $transfer->season_id = $this->seasonId;
        $transfer->source_club_id = $request->input('source_club_id');
        $transfer->player_id = $request->input('player_id');
        $transfer->transfer_type = TransferTypes::FREE_TRANSFER;
        $transfer->offer_date = Instance::where('id', $this->instanceId)->first()->instance_date;
        $transfer->source_club_status = TransferStatusTypes::WAITING_PLAYER;

        $transfer->save();

        $contractOffer = new TransferContractOffer;

        $contractOffer->transfer_id = $transfer->id;
        $contractOffer->salary = $request->input('salary');
        $contractOffer->appearance = $request->input('appearance');
        $contractOffer->assist = $request->input('assist');
        $contractOffer->goal = $request->input('goal');
        $contractOffer->league = $request->input('league');
        $contractOffer->pc_promotion_salary_raise = $request->input('pc_promotion_salary_raise');
        $contractOffer->pc_demotion_salary_cut = $request->input('pc_demotion_salary_cut');
        $contractOffer->cup = $request->input('cup');
        $contractOffer->el = $request->input('el');
        $contractOffer->salary = $request->input('agent_fee');

        $contractOffer->save();

        return $transfer;
    }

    public function updateTransferStatus(Transfer $transfer, int $sourceClubStatus): Transfer
    {
        $transfer->source_club_status = $sourceClubStatus;

        $transfer->save();

        return $transfer;
    }

    public function processMedical(Transfer $transfer): bool
    {
        $instance = Instance::where('id', $this->instanceId)->first();
        $playerInjury = DB::table('player_injuries as pi')->select(
            'it.severity'
        )
        ->join('injury_types as it', 'it.id', '=', 'pi.injury_id')
        ->where('pi.injury_end_date', '>=', $instance->instance_date)
        ->first();

        if ($playerInjury && $playerInjury->severity >= 4) {
            return false;
        }

        return true;
    }

    public function transferPlayerToNewClub(Transfer $transfer)
    {
        $player = Player::where('id', $transfer->player_id)->first();

        $player->club_id = $transfer->source_club_id;

        $player->save();

        // transfer offer contract to real contract

        // start financial transaction process - add event
    }
}
