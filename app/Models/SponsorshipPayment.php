<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorshipPayment extends Model
{
    use HasFactory;

    protected $fillable = ['sponsorship_contract_id', 'season_id', 'transaction_id', 'amount', 'paid_on'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_on' => 'immutable_date'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(SponsorshipContract::class, 'sponsorship_contract_id');
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinanceTransactionEntity::class, 'transaction_id');
    }
}
