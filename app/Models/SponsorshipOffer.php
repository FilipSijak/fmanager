<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGameInstance;
use App\Services\FinanceService\SponsorshipOfferStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorshipOffer extends Model
{
    use BelongsToGameInstance, HasFactory;

    protected $fillable = [
        'instance_id', 'club_id', 'season_id', 'sponsor_account_id', 'accepted_contract_id',
        'status', 'attempt_number', 'term_seasons', 'annual_amount', 'maximum_annual_amount',
        'negotiation_rounds', 'negotiation_response', 'offered_on', 'starts_at', 'expires_at', 'respond_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SponsorshipOfferStatus::class,
            'annual_amount' => 'integer', 'maximum_annual_amount' => 'integer',
            'attempt_number' => 'integer', 'term_seasons' => 'integer', 'negotiation_rounds' => 'integer',
            'offered_on' => 'immutable_date', 'starts_at' => 'immutable_date',
            'expires_at' => 'immutable_date', 'respond_by' => 'immutable_date',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function sponsorAccount(): BelongsTo
    {
        return $this->belongsTo(GameEntityAccount::class, 'sponsor_account_id');
    }

    public function acceptedContract(): BelongsTo
    {
        return $this->belongsTo(SponsorshipContract::class, 'accepted_contract_id');
    }
}
