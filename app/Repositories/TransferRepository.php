<?php

namespace App\Repositories;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\Transfer;
use App\Models\TransferContractOffer;
use App\Models\TransferFinancialDetails;
use App\Services\TransferService\Data\FreeTransferOfferData;
use App\Services\TransferService\Data\TransferOfferData;
use App\Services\TransferService\TransferEntityAnalysis\PlayerValuation;
use App\Services\TransferService\TransferState;
use App\Services\TransferService\TransferStatusTypes;
use App\Services\TransferService\TransferType;
use App\Support\GameContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class TransferRepository
{
    public function __construct(
        private readonly TransferState $transferState,
        private readonly GameContext $gameContext,
    ) {}

    public function createAutomaticTransfer(
        Player $player,
        Club $buyingClub,
        TransferType|int $transferType,
    ): Transfer {
        $transferType = $this->transferType($transferType);
        $transferStatus = match ($transferType) {
            TransferType::FREE_TRANSFER => TransferStatusTypes::WAITING_PLAYER,
            TransferType::PERMANENT_TRANSFER,
            TransferType::LOAN_TRANSFER => TransferStatusTypes::WAITING_TARGET_CLUB,
        };

        $this->assertBelongsToCurrentInstance($player, $buyingClub);

        return Transfer::query()->create([
            'instance_id' => $this->gameContext->instanceId(),
            'season_id' => $this->gameContext->seasonId(),
            'source_club_id' => $buyingClub->id,
            'player_id' => $player->id,
            'offer_date' => $this->currentInstance()->instance_date,
            'transfer_type' => $transferType->value,
            'transfer_status' => $transferStatus->value,
        ]);
    }

    public function createTransferFinancialDetails(
        Transfer $transfer,
        Player $player,
        Club $buyingClub,
        bool $urgentTransfer,
    ): TransferFinancialDetails {
        $amount = PlayerValuation::buyingClubValuation($player, $buyingClub, $urgentTransfer);

        if ($amount <= 0) {
            throw new LogicException('The buying club cannot afford this transfer.');
        }

        return TransferFinancialDetails::query()->create([
            'amount' => $amount,
            'transfer_id' => $transfer->id,
            'installments' => $this->setTransferInstallments($buyingClub, $amount),
        ]);

    }

    public function storeTransfer(TransferOfferData $offer): Transfer
    {
        $this->assertOfferEntitiesBelongToCurrentInstance(
            [$offer->sourceClubId, $offer->targetClubId],
            $offer->playerId
        );

        return DB::transaction(function () use ($offer): Transfer {
            $transfer = new Transfer;
            $transfer->instance_id = $this->gameContext->instanceId();
            $transfer->season_id = $this->gameContext->seasonId();
            $transfer->source_club_id = $offer->sourceClubId;
            $transfer->target_club_id = $offer->targetClubId;
            $transfer->player_id = $offer->playerId;
            $transfer->offer_date = $this->currentInstance()->instance_date;
            $transfer->transfer_type = $offer->transferType->value;
            $transfer->transfer_status = TransferStatusTypes::WAITING_TARGET_CLUB->value;
            $transfer->save();

            TransferFinancialDetails::query()->create([
                'transfer_id' => $transfer->id,
                'amount' => $offer->amount,
                'installments' => $offer->installments,
            ]);

            return $transfer;
        });
    }

    public function storeFreeTransfer(FreeTransferOfferData $offer): Transfer
    {
        $this->assertOfferEntitiesBelongToCurrentInstance([$offer->sourceClubId], $offer->playerId);

        return DB::transaction(function () use ($offer): Transfer {
            $transfer = new Transfer;
            $transfer->instance_id = $this->gameContext->instanceId();
            $transfer->season_id = $this->gameContext->seasonId();
            $transfer->source_club_id = $offer->sourceClubId;
            $transfer->player_id = $offer->playerId;
            $transfer->transfer_type = TransferType::FREE_TRANSFER->value;
            $transfer->offer_date = $this->currentInstance()->instance_date;
            $transfer->transfer_status = TransferStatusTypes::WAITING_PLAYER->value;
            $transfer->save();

            $contractOffer = new TransferContractOffer;
            $contractOffer->transfer_id = $transfer->id;
            $contractOffer->transfer_fee = $offer->transferFee;
            $contractOffer->salary = $offer->salary;
            $contractOffer->appearance = $offer->appearance;
            $contractOffer->assist = $offer->assist;
            $contractOffer->goal = $offer->goal;
            $contractOffer->league = $offer->league;
            $contractOffer->pc_promotion_salary_raise = $offer->promotionSalaryRaise;
            $contractOffer->pc_demotion_salary_cut = $offer->demotionSalaryCut;
            $contractOffer->cup = $offer->cup;
            $contractOffer->el = $offer->el;
            $contractOffer->save();

            return $transfer;
        });
    }

    public function updateTransferStatus(Transfer $transfer, TransferStatusTypes|int $status): Transfer
    {
        $this->transferState->transitionTo(
            $transfer,
            $status instanceof TransferStatusTypes ? $status : TransferStatusTypes::from($status)
        );

        return $transfer;
    }

    public function removeTransferAndPlayerOffers(Transfer $transfer): void
    {
        DB::transaction(function () use ($transfer): void {
            $this->removeTransferContractOffer($transfer);
            $this->removeTransferFinancialDetails($transfer);
            $transfer->delete();
        });
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

    private function setTransferInstallments(Club $club, int $amount): int
    {
        $transferBudget = (int) $club->account()->value('transfer_budget');

        return $amount > $transferBudget / 2 ? 24 : 0;
    }

    private function transferType(TransferType|int $transferType): TransferType
    {
        if ($transferType instanceof TransferType) {
            return $transferType;
        }

        return TransferType::tryFrom($transferType)
            ?? throw new InvalidArgumentException("Unsupported transfer type: {$transferType}");
    }

    private function currentInstance(): Instance
    {
        return Instance::query()->findOrFail($this->gameContext->instanceId());
    }

    private function assertBelongsToCurrentInstance(Player $player, Club $club): void
    {
        $instanceId = $this->gameContext->instanceId();
        if ((int) $player->instance_id !== $instanceId || (int) $club->instance_id !== $instanceId) {
            throw new InvalidArgumentException('The player and buying club must belong to the active instance.');
        }
    }

    /** @param list<int> $clubIds */
    private function assertOfferEntitiesBelongToCurrentInstance(array $clubIds, int $playerId): void
    {
        $instanceId = $this->gameContext->instanceId();
        $clubsExist = Club::query()
            ->whereIn('id', $clubIds)
            ->where('instance_id', $instanceId)
            ->count() === count(array_unique($clubIds));
        $playerExists = Player::query()
            ->whereKey($playerId)
            ->where('instance_id', $instanceId)
            ->where('is_retired', false)
            ->exists();

        if (! $clubsExist || ! $playerExists) {
            throw new InvalidArgumentException('Transfer clubs and player must belong to the active instance.');
        }
    }
}
