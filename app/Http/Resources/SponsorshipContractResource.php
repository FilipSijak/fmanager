<?php

namespace App\Http\Resources;

use App\Models\SponsorshipContract;
use App\Support\GameContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SponsorshipContract */
class SponsorshipContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $date = CarbonImmutable::parse(app(GameContext::class)->instanceDate())->toDateString();

        return [
            'id' => $this->id,
            'sponsor_name' => $this->sponsorAccount->gameEntity->name,
            'annual_amount' => $this->annual_amount,
            'starts_at' => $this->starts_at->toDateString(),
            'expires_at' => $this->expires_at->toDateString(),
            'status' => $date < $this->starts_at->toDateString() ? 'upcoming' : ($date > $this->expires_at->toDateString() ? 'expired' : 'active'),
            'paid_this_season' => (int) ($this->paid_this_season ?? 0),
            'payment_frequency' => 'season_start',
        ];
    }
}
