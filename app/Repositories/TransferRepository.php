<?php

namespace App\Repositories;

use App\Http\Requests\CreateTransferRequest;
use App\Http\Requests\FreeTransferRequest;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\Transfer;
use App\Models\TransferContractOffer;
use App\Models\TransferFinancialDetails;
use App\Services\TransferService\TransferEntityAnalysis\PlayerValuation;
use App\Services\TransferService\TransferFinancialSettlement;
use App\Services\TransferService\TransferStatusTypes;
use App\Services\TransferService\TransferTypes;
use Illuminate\Support\Facades\DB;

class TransferRepository extends CoreRepository
{
    private PlayerRepository $playerRepository;

    public function __construct(PlayerRepository $playerRepository)
    {
        $this->playerRepository = $playerRepository;
    }

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
        ->join('players as p', 'p.id', '=', 'pi.player_id')
        ->where('pi.injury_end_date', '>=', $instance->instance_date)
        ->where('p.id', '=', $transfer->player_id)
        ->first();

        if ($playerInjury && $playerInjury->severity >= 4) {
            return false;
        }

        return true;
    }

    public function transferPlayerToNewClub(Transfer $transfer)
    {
        // if outside of transfer window, update status for

        $player = Player::where('id', $transfer->player_id)->first();

        if ($transfer->transfer_type == TransferTypes::LOAN_TRANSFER) {
            $player->loan_club_id = $transfer->source_club_id;;
            $player->update();

            return;
        }

        $player->club_id = $transfer->source_club_id;

        $transferContractOffer = TransferContractOffer::where('transfer_id', $transfer->id)->first();

        if ($transfer->transfer_type == TransferTypes::FREE_TRANSFER) {
            $currentContract = new PlayerContract($transferContractOffer->toArray());
            $currentContract->save();
        } else {
            $currentContract = $player->contract()->first();
            $transferFinancialSettlement = new TransferFinancialSettlement;
            $transferFinancialSettlement->transferMoneyBetweenClubs($transfer);
        }

        $currentContract->update($transferContractOffer->toArray());

        $transferContractOffer->delete();

        $player->club_id = $transfer->source_club_id;
        $player->save();
    }

    public function makeAutomaticTransferWithFinancialDetails(
        Player $player,
        Club $club, // buying club
        int $transferType = TransferTypes::PERMANENT_TRANSFER
    ): Transfer|null {
        $transfer = new Transfer([
            'season_id' => $this->seasonId,
            'source_club_id' => $club->id,
            'player_id' => $player->id,
            'offer_date' => Instance::find($this->instanceId)->instance_date,
            'transfer_type' => $transferType,
        ]);

        if ($transferType != TransferTypes::FREE_TRANSFER) {
            $transfer->target_club_id = $player->club_id;
        }

        $transfer->save();

        if ($transferType != TransferTypes::FREE_TRANSFER) {
            TransferFinancialDetails::create([
                'amount' => $player->value,
                'transfer_id' => $transfer->id,
                'installments' => 0,
            ]);
        }

        $this->makePlayerContractOffer($transfer);

        return $transfer;
    }

    public function transferFeeCounterOffer(Transfer $transfer, int $type)
    {
        if ($type === TransferStatusTypes::SOURCE_CLUB_COUNTEROFFER) {
            // target club approves or declines transfer or updates with a counteroffer
            // we only check for financial side and no other squad deficits at that point

            $decision = true;

            if ($decision) {
                $this->updateTransferStatus($transfer,TransferStatusTypes::COUNTEROFFER_ACCEPTED);
            }
        } else {
            if ($transfer->canClubAffordTransfer(Club::find($transfer->source_club_id))) {
                // check if source club can afford the counter offer
                // check source club max player valuation
            }
        }
        // routes for source or target club
        // if it's a source club counteroffer, target club has to review, and vice versa
        // we only check for financial side and no other squad deficits at that point
        // update transfer to decline or TARGET_CLUB_COUNTEROFFER_ACCEPTED
    }

    public function makePlayerContractOffer(Transfer $transfer)
    {
        $player = Player::find($transfer->player_id);

        $contractOffer = $this->playerRepository->contractBasedOnPotential($player);

        DB::table('transfer_contract_offers')->insert(
            [
                'transfer_id' => $transfer->id,
                'salary' => $contractOffer['salary'],
                'appearance' => $contractOffer['appearance'],
                'clean_sheet' => $contractOffer['clean_sheet'],
                'goal' => $contractOffer['goal'],
                'assist' => $contractOffer['assist'],
                'league' => $contractOffer['league'],
                'promotion' => $contractOffer['promotion'],
                'cup' => $contractOffer['cup'],
                'el' => $contractOffer['el'],
                'cl' => $contractOffer['cl'],
                'pc_promotion_salary_raise' => $contractOffer['salary_raise'],
                'pc_demotion_salary_cut' => $contractOffer['demotion']
            ]
        );

        $this->updateTransferStatus($transfer,TransferStatusTypes::WAITING_PLAYER);
    }

    public function removeTransferAndPlayerOffers(Transfer $transfer)
    {

    }
}
