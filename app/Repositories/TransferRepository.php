<?php

namespace App\Repositories;

use App\Http\Requests\CreateTransferRequest;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerInjury;
use App\Models\Transfer;
use App\Models\TransferFinancialDetails;
use App\Services\TransferService\TransferStatusTypes;
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
