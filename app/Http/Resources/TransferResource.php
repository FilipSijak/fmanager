<?php

namespace App\Http\Resources;

use App\Services\TransferService\TransferManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $clubId = (int) $this->instance->club_id;
        $status = (int) $this->transfer_status;

        return [
            'id' => $this->id,
            'player' => ['id' => $this->player_id, 'name' => trim($this->player?->person?->first_name.' '.$this->player?->person?->last_name), 'position' => $this->player?->position],
            'buyer' => ['id' => $this->source_club_id, 'name' => $this->sourceClub?->name],
            'seller' => $this->targetClub ? ['id' => $this->target_club_id, 'name' => $this->targetClub->name] : null,
            'direction' => (int) $this->source_club_id === $clubId ? 'incoming' : 'outgoing',
            'type' => (int) $this->transfer_type,
            'status' => $status,
            'status_label' => match ($status) {
                1 => 'Awaiting club response', 2 => 'Awaiting player response', 3 => 'Medical and paperwork',
                4 => 'Waiting for transfer window', 5 => 'Ready to complete', 6, 7 => 'Club counteroffer',
                8 => 'Fee agreed', 9, 10 => 'Player counteroffer', 11 => 'Contract offer needed',
                12 => 'Player declined', 13 => 'Club declined', 14 => 'Completed', 15 => 'Closed',
                default => 'Unknown status',
            },
            'amount' => (int) ($this->transferFinancialDetails?->amount ?? 0),
            'installments' => (int) ($this->transferFinancialDetails?->installments ?? 0),
            'contract' => $this->transferContractOffer ? collect($this->transferContractOffer->only(TransferManagementService::CONTRACT_FIELDS))->map(fn ($value): int => (int) $value)->all() : null,
            'offered_on' => $this->offer_date,
            'transfer_date' => $this->transfer_date,
            'actions' => TransferManagementService::availableActions($this->resource, $clubId),
        ];
    }
}
