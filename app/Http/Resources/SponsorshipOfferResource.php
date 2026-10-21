<?php

namespace App\Http\Resources;

use App\Models\SponsorshipOffer;
use App\Services\FinanceService\SponsorshipOfferStatus;
use App\Support\GameContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SponsorshipOffer */
class SponsorshipOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $expired = $this->status === SponsorshipOfferStatus::Pending
            && $this->respond_by->toDateString() < app(GameContext::class)->instanceDate();

        return [
            'id' => $this->id, 'sponsor_name' => $this->sponsorAccount->gameEntity->name,
            'sponsor_rank' => $this->sponsorAccount->gameEntity->rank,
            'annual_amount' => $this->annual_amount, 'term_seasons' => $this->term_seasons,
            'starts_at' => $this->starts_at->toDateString(), 'expires_at' => $this->expires_at->toDateString(),
            'respond_by' => $this->respond_by->toDateString(), 'status' => $expired ? SponsorshipOfferStatus::Expired->value : $this->status->value,
            'attempt_number' => $this->attempt_number, 'negotiation_rounds' => $this->negotiation_rounds,
            'negotiation_response' => $this->negotiation_response,
            'is_final' => $this->resource->isFinal(),
            'offered_on' => $this->offered_on->toDateString(),
            'rejected_on' => $this->rejected_on?->toDateString(),
        ];
    }
}
