<?php

namespace App\Services\TransferService;

use App\Models\Instance;
use App\Models\Player;
use App\Models\Transfer;
use App\Repositories\TransferRepository;
use App\Services\TransferService\Data\FreeTransferOfferData;
use App\Services\TransferService\Data\TransferOfferData;
use App\Support\GameContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferManagementService
{
    public const array TERMINAL_STATUSES = [12, 13, 14, 15];

    public const array CONTRACT_FIELDS = ['salary', 'transfer_fee', 'appearance', 'goal', 'assist', 'clean_sheet', 'league', 'promotion', 'cup', 'el', 'cl'];

    public function __construct(
        private readonly GameContext $context,
        private readonly TransferRepository $transfers,
        private readonly TransferState $state,
        private readonly TransferWorkflow $workflow,
    ) {}

    public function managedInstance(): Instance
    {
        return Instance::query()->with('club.account')->findOrFail($this->context->instanceId());
    }

    public function forManagedClub(): Builder
    {
        $clubId = $this->managedInstance()->club_id;

        return Transfer::query()->with(['player.person', 'sourceClub', 'targetClub', 'transferFinancialDetails', 'transferContractOffer', 'instance'])
            ->where('instance_id', $this->context->instanceId())
            ->where(fn (Builder $query) => $query->where('source_club_id', $clubId)->orWhere('target_club_id', $clubId))
            ->orderByDesc('id');
    }

    public function offerPlayer(int $playerId): Player
    {
        $player = Player::query()->with(['person', 'club', 'contract'])
            ->where('instance_id', $this->context->instanceId())->findOrFail($playerId);

        if ($player->is_retired || $player->club_id === $this->managedInstance()->club_id || $player->loan_club_id !== null) {
            throw ValidationException::withMessages(['player_id' => 'This player is not available for an offer.']);
        }

        return $player;
    }

    /** @param array<string, mixed> $data */
    public function offer(array $data): Transfer
    {
        return DB::transaction(function () use ($data): Transfer {
            $instance = $this->managedInstance();
            $player = Player::query()->where('instance_id', $instance->id)->lockForUpdate()->findOrFail($data['player_id']);
            $this->offerPlayer($player->id);
            $free = $player->club_id === null;

            if ((int) $data['transfer_type'] !== ($free ? 3 : 1)) {
                throw ValidationException::withMessages(['transfer_type' => 'Choose a permanent offer for a contracted player or a free transfer for a free agent.']);
            }

            $duplicate = Transfer::query()->where('instance_id', $instance->id)->where('season_id', $instance->season_id)
                ->where('source_club_id', $instance->club_id)->where('player_id', $player->id)
                ->whereNotIn('transfer_status', self::TERMINAL_STATUSES)->exists();

            if ($duplicate) {
                throw ValidationException::withMessages(['player_id' => 'You already have an active offer for this player.']);
            }

            $this->ensureAffordable((int) ($free ? ($data['transfer_fee'] ?? 0) : $data['amount']));
            $data['source_club_id'] = $instance->club_id;
            $data['target_club_id'] = $player->club_id;
            $transfer = $free
                ? $this->transfers->storeFreeTransfer(FreeTransferOfferData::fromArray($data))
                : $this->transfers->storeTransfer(TransferOfferData::fromArray($data));

            if ($free) {
                $transfer->transferContractOffer()->update(collect($data)->only(self::CONTRACT_FIELDS)->all());
            }

            return $transfer;
        });
    }

    /** @return list<string> */
    public static function availableActions(Transfer $transfer, int $clubId): array
    {
        if ((int) $transfer->season_id !== (int) $transfer->instance->season_id) {
            return [];
        }

        $status = (int) $transfer->transfer_status;

        if ((int) $transfer->target_club_id === $clubId && $status === 1) {
            return ['accept', 'counter', 'reject'];
        }

        if ((int) $transfer->source_club_id !== $clubId) {
            return [];
        }

        return match ($status) {
            7 => ['accept', 'counter', 'reject'],
            9 => ['accept', 'contract', 'reject'],
            11 => ['contract', 'withdraw'],
            1, 2, 6, 8 => ['withdraw'],
            default => [],
        };
    }

    /** @param array<string, mixed> $data */
    public function respond(int $transferId, array $data): Transfer
    {
        return DB::transaction(function () use ($transferId, $data): Transfer {
            $instance = $this->managedInstance();
            $transfer = Transfer::query()->with('instance')->where('instance_id', $instance->id)
                ->where(fn (Builder $query) => $query->where('source_club_id', $instance->club_id)->orWhere('target_club_id', $instance->club_id))
                ->lockForUpdate()->findOrFail($transferId);

            if (! in_array($data['action'], self::availableActions($transfer, (int) $instance->club_id), true)) {
                throw ValidationException::withMessages(['action' => 'This offer has changed or is no longer awaiting your response.']);
            }

            $buyer = (int) $transfer->source_club_id === (int) $instance->club_id;
            $player = Player::query()->where('instance_id', $instance->id)->lockForUpdate()->findOrFail($transfer->player_id);
            $expectedClub = (int) $transfer->transfer_type === 3 ? null : $transfer->target_club_id;

            if (in_array($data['action'], ['accept', 'counter', 'contract'], true)
                && ($player->is_retired || $player->club_id !== $expectedClub || $player->loan_club_id !== null)) {
                throw ValidationException::withMessages(['action' => 'The player is no longer available for this transfer.']);
            }

            if (in_array($data['action'], ['reject', 'withdraw'], true)) {
                $this->state->transitionTo($transfer, $buyer ? TransferStatusTypes::TRANSFER_FAILED : TransferStatusTypes::TARGET_CLUB_DECLINED);
            } elseif ($data['action'] === 'counter') {
                if ($buyer) {
                    $this->ensureAffordable((int) $data['amount']);
                }
                $transfer->transferFinancialDetails()->update(['amount' => $data['amount']]);
                $this->state->transitionTo($transfer, $buyer ? TransferStatusTypes::WAITING_TARGET_CLUB : TransferStatusTypes::TARGET_CLUB_COUNTEROFFER);
            } elseif ($data['action'] === 'contract') {
                $this->ensureAffordable((int) ($transfer->transferFinancialDetails?->amount ?? 0) + (int) ($data['transfer_fee'] ?? $transfer->transferContractOffer?->transfer_fee ?? 0));
                $transfer->transferContractOffer()->update(collect($data)->only(self::CONTRACT_FIELDS)->all());
                $this->state->transitionTo($transfer, TransferStatusTypes::WAITING_PLAYER);
            } elseif (! $buyer) {
                $this->workflow->makePlayerContractOffer($transfer);
            } elseif ((int) $transfer->transfer_status === 7) {
                $this->ensureAffordable((int) $transfer->transferFinancialDetails->amount);
                $this->state->transitionTo($transfer, TransferStatusTypes::COUNTEROFFER_ACCEPTED);
                $this->workflow->makePlayerContractOffer($transfer);
            } else {
                $this->ensureAffordable((int) ($transfer->transferFinancialDetails?->amount ?? 0) + (int) $transfer->transferContractOffer->transfer_fee);
                $this->state->transitionTo($transfer, TransferStatusTypes::WAITING_PAPERWORK);
            }

            return $transfer;
        });
    }

    private function ensureAffordable(int $amount): void
    {
        $budget = (int) ($this->managedInstance()->club->account?->transfer_budget ?? 0);

        if ($amount > $budget) {
            throw ValidationException::withMessages(['amount' => 'The offer exceeds your available transfer budget.']);
        }
    }
}
