<?php

namespace App\Services\TransferService;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\Transfer;
use App\Models\TransferContractOffer;
use App\Models\TransferFinancialDetails;
use App\Repositories\PlayerRepository;
use App\Repositories\TransferRepository;
use App\Services\TransferService\TransferConsiderations\TransferConsiderations;
use App\Services\TransferService\TransferEntityAnalysis\PlayerValuation;
use App\Services\TransferService\TransferWindowConfig\TransferWindowAvailability;
use Illuminate\Support\Facades\DB;

class TransferWorkflow
{
    private TransferRepository $transferRepository;
    private PlayerRepository $playerRepository;
    private TransferFinancialSettlement $transferFinancialSettlement;
    private TransferConsiderations $transferConsiderations;

    public function __construct(
        TransferRepository $transferRepository,
        PlayerRepository $playerRepository,
        TransferFinancialSettlement $transferFinancialSettlement,
        TransferConsiderations $transferConsiderations
    )
    {
        $this->transferRepository = $transferRepository;
        $this->playerRepository = $playerRepository;
        $this->transferFinancialSettlement = $transferFinancialSettlement;
        $this->transferConsiderations = $transferConsiderations;
    }

    public function transferPlayerToNewClub(Transfer $transfer): void
    {
        $instance = Instance::findOrFail($transfer->instance_id);

        if (!TransferWindowAvailability::isTransferWindowOpen($instance->instance_date)) {
            $transfer->transfer_date = TransferWindowAvailability::nextAvailableTransferWindow($instance->instance_date);

            $transfer->save();
            $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::WAITING_TRANSFER_WINDOW->value);

            return;
        }

        $transferContractOffer = $transfer->transferContractOffer()->firstOrFail();

        if ($transfer->transfer_type != TransferTypes::LOAN_TRANSFER &&
            !$this->checkTransferAffordabilityBeforeCompletion($transfer, $transferContractOffer)) {
            return;
        }

        try {

            DB::transaction(function () use ($transfer, $transferContractOffer) {
                match ($transfer->transfer_type) {
                    TransferTypes::LOAN_TRANSFER => $this->handleLoanTransferPlayerMove($transfer),
                    TransferTypes::FREE_TRANSFER => $this->handleFreeTransferPlayerMove($transfer, $transferContractOffer),
                    TransferTypes::PERMANENT_TRANSFER => $this->handlePermanentTransferPlayerMove($transfer, $transferContractOffer)
                };

                $transferContractOffer->delete();
                $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::TRANSFER_COMPLETED->value);
            });

        } catch (\Exception $e) {
            // log error @todo
            throw $e;
        }
    }

    public function transferFeeCounterOffer(Transfer $transfer): void
    {
        $player = Player::find($transfer->player_id);
        $buyingClub = Club::find($transfer->source_club_id);
        $urgentTransfer = true;
        $buyingClubValuation = PlayerValuation::buyingClubValuation($player, $buyingClub, $urgentTransfer);
        $transferAmount = $transfer->transferFinancialDetails()->first()?->amount ?? 0;
        $valuationComparison = $buyingClubValuation >= $transferAmount;

        if ($valuationComparison && $this->canClubAffordTransfer($transfer, $buyingClub)) {
            $this->transferRepository->updateTransferStatus($transfer,TransferStatusTypes::COUNTEROFFER_ACCEPTED->value);

            return;
        }

        $this->transferRepository->updateTransferStatus($transfer,TransferStatusTypes::TRANSFER_FAILED->value);
        // @todo send news
    }

    public function sellingClubDecision(Transfer $transfer):void
    {
        if ($this->transferConsiderations->sellingClubDecision($transfer)) {
            // @todo update news source club accepted

            $this->makePlayerContractOffer($transfer);
        }
    }

    public function playerDecision(Transfer $transfer): void
    {
        $this->transferConsiderations->playerDecision($transfer);
    }

    public function waitingPaperwork(Transfer $transfer): void
    {
        if (!$this->processMedical($transfer)) {
            $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::TRANSFER_FAILED->value);

            return;
        }

        $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::MOVE_PLAYER->value);
    }

    public function playerCounterOffer(Transfer $transfer): void
    {
        $this->transferConsiderations->playerCounterOffer($transfer);
    }

    public function targetClubDeclined(Transfer $transfer): void
    {
        $this->transferRepository->updateTransferStatus(
            $transfer,
            TransferStatusTypes::TRANSFER_FAILED->value
        );

        //@todo update news
    }

    public function removeTransferContractOffer(Transfer $transfer): void
    {
        $this->transferRepository->removeTransferContractOffer($transfer);
    }

    public function removeTransferAndPlayerOffers(Transfer $transfer): void
    {
        $this->transferRepository->removeTransferAndPlayerOffers($transfer);
    }

    public function playerDeclined(Transfer $transfer): void
    {
        $this->transferRepository->updateTransferStatus(
            $transfer,
            TransferStatusTypes::TRANSFER_FAILED->value
        );

        //@todo update news
    }


    public function processMedical(Transfer $transfer): bool
    {
        $instance = Instance::findOrFail($transfer->instance_id);
        $playerInjury = DB::table('player_injuries as pi')
            ->select('i.severity')
            ->join('injuries as i', 'i.id', '=', 'pi.injury_id')
            ->join('players as p', 'p.id', '=', 'pi.player_id')
            ->where('pi.injury_end_date', '>=', $instance->instance_date)
            ->where('pi.season_id', '=', $transfer->season_id)
            ->where('pi.instance_id', '=', $transfer->instance_id)
            ->where('p.id', '=', $transfer->player_id)
            ->first();

        if ($playerInjury && $playerInjury->severity >= 4) {
            return false;
        }

        return true;
    }

    public function makeAutomaticTransferWithFinancialDetails(
        Player $player,
        Club $buyingClub,
        int $transferType = TransferTypes::PERMANENT_TRANSFER,
        bool $urgentTransfer = false,
    ): Transfer|null {
        $transfer = $this->transferRepository->createAutomaticTransfer(
            $player,
            $buyingClub,
            $transferType,
        );

        if ($transferType != TransferTypes::FREE_TRANSFER) {
            $transfer->target_club_id = $player->club_id;
        }

        $transfer->save();

        if ($transferType != TransferTypes::FREE_TRANSFER) {
            $this->transferRepository->createTransferFinancialDetails($transfer, $player, $buyingClub, $urgentTransfer);
        }

        return $transfer;
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

        $this->transferRepository->updateTransferStatus($transfer,TransferStatusTypes::WAITING_PLAYER->value);
    }

    private function canClubAffordTransfer(Transfer $transfer, Club $club): bool
    {
        $transferAmount = $transfer->transferFinancialDetails()->first()?->amount;
        $transferBudget = $club->account()->first()?->transfer_budget;

        return $transferAmount !== null && $transferBudget !== null && $transferBudget >= $transferAmount;
    }

    private function handleLoanTransferPlayerMove(Transfer $transfer): void
    {
        $player = $transfer->player()->first();

        $player->loan_club_id = $transfer->source_club_id;
        $player->update();

        // @todo send news
    }

    private function handleFreeTransferPlayerMove(Transfer $transfer, TransferContractOffer $transferContractOffer): void
    {
        $player = $transfer->player()->first();
        $offeredContract = new PlayerContract($transferContractOffer->toArray());

        $offeredContract->save();
        $player->contract_id = $offeredContract->id;
        $player->club_id = $transfer->source_club_id;

        $player->update();
        // news @todo
    }

    private function handlePermanentTransferPlayerMove(Transfer $transfer, TransferContractOffer $transferContractOffer): void
    {
        $player = $transfer->player()->first();
        $currentContract = $player->contract()->first();

        $player->club_id = $transfer->source_club_id;
        $this->transferFinancialSettlement->transferMoneyBetweenClubs($transfer);
        $currentContract->update($transferContractOffer->toArray());

        $player->save();
        // send news @todo
    }

    private function checkTransferAffordabilityBeforeCompletion(Transfer $transfer, TransferContractOffer $transferContractOffer): bool
    {
        $transferFinancialDetails = TransferFinancialDetails::where('transfer_id', $transfer->id)->first();
        $transferAmount = $transferFinancialDetails ? $transferFinancialDetails->amount : 0;
        $sourceClub = Club::find($transfer->source_club_id);
        $sourceClubAccount = $sourceClub->account()->first();

        if (
            $sourceClubAccount->transfer_budget < ($transferAmount + $transferContractOffer->transfer_fee)
        ) {
            $this->transferRepository->updateTransferStatus($transfer, TransferStatusTypes::TRANSFER_FAILED->value);

            // send news @todo

            return false;
        }

        return true;
    }
}
