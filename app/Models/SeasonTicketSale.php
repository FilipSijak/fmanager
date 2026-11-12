<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGameInstance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeasonTicketSale extends Model
{
    use BelongsToGameInstance;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['capacity' => 'integer', 'quantity' => 'integer', 'home_games' => 'integer',
            'league_ticket_price' => 'integer', 'ticket_price' => 'integer', 'amount' => 'integer', 'sold_on' => 'date'];
    }

    public function scopeForClub(Builder $query, Club $club): Builder
    {
        return $query->forInstance($club->instance_id)->where('club_id', $club->id);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinanceTransactions::class, 'transaction_id');
    }
}
