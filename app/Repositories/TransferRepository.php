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
use App\Services\TransferService\TransferStatusTypes;
use App\Services\TransferService\TransferTypes;
use App\Services\TransferService\TransferWindowConfig\TransferWindowAvailability;
use Illuminate\Support\Facades\DB;

class TransferRepository extends CoreRepository
{
    private PlayerRepository $playerRepository;
    private FinanceService $financeService;
    private TransferFinancialSettlement $transferFinancialSettlement;

    public function __construct(
        PlayerRepository $playerRepository,
        FinanceService $financeService,
        TransferFinancialSettlement $transferFinancialSettlement
    )
    {
        $this->playerRepository = $playerRepository;
        $this->financeService = $financeService;
        $this->transferFinancialSettlement = $transferFinancialSettlement;
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
        $transfer->transfer_status = $sourceClubStatus;

        $transfer->save();

        return $transfer;
    }

    public function processMedical(Transfer $transfer): bool
    {
        $instance = Instance::findOrFail($this->instanceId());
        $playerInjury = DB::table('player_injuries as pi')
        ->select('i.severity')
        ->join('injuries as i', 'i.id', '=', 'pi.injury_id')
        ->join('players as p', 'p.id', '=', 'pi.player_id')
        ->where('pi.injury_end_date', '>=', $instance->instance_date)
        ->where('pi.season_id', '=', $this->seasonId())
        ->where('pi.instance_id', '=', $this->instanceId())
        ->where('p.id', '=', $transfer->player_id)
        ->first();

        if ($playerInjury && $playerInjury->severity >= 4) {
            return false;
        }

        return true;
    }



    public function transferPlayerToNewClub(Transfer $transfer): void
    {

        // if outside of transfer window, update status for
        // if it's outside of the transfer window, transfer date should move to the next transfer window (update transfer_date on transfers table)
        $instance = Instance::findOrFail($this->instanceId());

        if (!TransferWindowAvailability::isTransferWindowOpen($instance->instance_date)) {
            // update transfer
            $transfer->transfer_date = TransferWindowAvailability::nextAvailableTransferWindow($instance->instance_date);

            $transfer->save();
            $this->updateTransferStatus($transfer, TransferStatusTypes::WAITING_TRANSFER_WINDOW->value);

            return;
        }

        // validation
        // check if the source club has enough money

        $sourceClub = Club::find($transfer->source_club_id);
        $sourceClubAccount = $sourceClub->account()->first();
        $transferFinancialDetails = TransferFinancialDetails::where('transfer_id', $transfer->id)->first();
        $transferContractOffer = TransferContractOffer::where('transfer_id', $transfer->id)->firstOrFail();
        $transferAmount = $transferFinancialDetails ? $transferFinancialDetails->amount : 0;

        if (
            $transfer->transfer_type != TransferTypes::LOAN_TRANSFER &&
            $sourceClubAccount->transfer_budget < ($transferAmount + $transferContractOffer->transfer_fee)
        ) {
            $this->updateTransferStatus($transfer, TransferStatusTypes::TRANSFER_FAILED->value);

            // send news @todo

            return;
        }

        try {
            DB::beginTransaction();

            if ($transfer->transfer_type == TransferTypes::LOAN_TRANSFER) {
                $this->handleLoanTransferPlayerMove($transfer);
            } elseif ($transfer->transfer_type == TransferTypes::FREE_TRANSFER) {
                $this->handleFreeTransferPlayerMove($transfer);
            } else {
                $player = $transfer->player()->first();
                $currentContract = $player->contract()->first();

                $player->club_id = $transfer->source_club_id;
                $this->transferFinancialSettlement->transferMoneyBetweenClubs($transfer);
                $currentContract->update($transferContractOffer->toArray());

                $player->save();
            }

            // cleanup

            $transferContractOffer->delete();

            // complete transfer
            $this->updateTransferStatus($transfer, TransferStatusTypes::TRANSFER_COMPLETED->value);

            // send news @todo

            DB::commit();
        } catch (\Exception $e) {
            // log error @todo
            DB::rollBack();

            throw $e;
        }

    }

    public function makeAutomaticTransferWithFinancialDetails(
        Player $player,
        Club $buyingClub,
        int $transferType = TransferTypes::PERMANENT_TRANSFER,
        bool $urgentTransfer = false,
    ): Transfer|null {
        $transfer = new Transfer([
            'instance_id' => $this->instanceId(),
            'season_id' => $this->seasonId(),
            'source_club_id' => $buyingClub->id,
            'player_id' => $player->id,
            'offer_date' => Instance::find($this->instanceId())->instance_date,
            'transfer_type' => $transferType,
        ]);

        if ($transferType != TransferTypes::FREE_TRANSFER) {
            $transfer->target_club_id = $player->club_id;
        }

        $transfer->save();

        if ($transferType != TransferTypes::FREE_TRANSFER) {
            TransferFinancialDetails::create([
                'amount' => PlayerValuation::buyingClubValuation($player, $buyingClub, $urgentTransfer),
                'transfer_id' => $transfer->id,
                'installments' => $this->setTransferInstallments($transfer, $buyingClub),
            ]);
        }

        return $transfer;
    }

    public function transferFeeCounterOffer(Transfer $transfer)
    {
        $player = Player::find($transfer->player_id);
        $buyingClub = Club::find($transfer->source_club_id);
        $urgentTransfer = true;
        $buyingClubValuation = PlayerValuation::buyingClubValuation($player, $buyingClub, $urgentTransfer);
        $transferAmount = $transfer->transferFinancialDetails()->first()?->amount ?? 0;
        $valuationComparison = $buyingClubValuation >= $transferAmount;

        if ($valuationComparison && $this->canClubAffordTransfer($transfer, $buyingClub)) {
            $this->updateTransferStatus($transfer,TransferStatusTypes::COUNTEROFFER_ACCEPTED->value);

            return;
        }

        $this->updateTransferStatus($transfer,TransferStatusTypes::TRANSFER_FAILED->value);
        // @todo send news
    }

    private function canClubAffordTransfer(Transfer $transfer, Club $club): bool
    {
        $transferAmount = $transfer->transferFinancialDetails()->first()?->amount;
        $transferBudget = $club->account()->first()?->transfer_budget;

        return $transferAmount !== null && $transferBudget !== null && $transferBudget >= $transferAmount;
    }

    public function makePlayerContractOffer(Transfer $transfer)
    {
        $player = Player::find($transfer->player_id);

        $contractOffer = $this->playerRepository->contractBasedOnPotential($player);

        DB::table('transfer_contract_offers')->insert(
            [
                'transfer_id' => $transfer->id,
                'transfer_fee' => $contractOffer['transfer_fee'],
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
                'pc_demotion_salary_cut' => $contractOffer['demotion'],
                'counter_offered' => 0
            ]
        );

        $this->updateTransferStatus($transfer,TransferStatusTypes::WAITING_PLAYER->value);
    }

    public function removeTransferAndPlayerOffers(Transfer $transfer)
    {
        $this->removeTransferContractOfferA($transfer);
        $this->removeTransferFinancialDetails($transfer);
        $transfer->delete();
    }

    public function removeTransferContractOfferA(Transfer $transfer)
    {
        $contractOffer = $transfer->transferContractOffer()->first();

        $contractOffer?->delete();
    }

    public function removeTransferFinancialDetails(Transfer $transfer)
    {
        $transferFinancialDetails = $transfer->transferFinancialDetails()->first();

        $transferFinancialDetails?->delete();
    }

    private function setTransferInstallments(Transfer $transfer, Club $club): int
    {
        $account = $club->account()->first();

        if ($account->transfer_budget / 2 < $transfer->amount) {
            return 24;
        }

        return 0;
    }

    private function handleFreeTransferPlayerMove(Transfer $transfer)
    {
        $transferContractOffer = TransferContractOffer::where('transfer_id', $transfer->id)->firstOrFail();
        $player = $transfer->player()->first();
        $offeredContract = new PlayerContract($transferContractOffer->toArray());

        $offeredContract->save();
        $player->contract_id = $offeredContract->id;
        $player->club_id = $transfer->source_club_id;

        $player->update();
    }

    private function handleLoanTransferPlayerMove(Transfer $transfer)
    {
        $player = $transfer->player()->first();

        $player->loan_club_id = $transfer->source_club_id;
        $player->update();
    }
}
