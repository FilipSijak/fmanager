<?php

namespace App\Models;

use App\EntityTransactionDirection;
use App\EntityTransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceTransactionEntity extends Model
{
    use HasFactory;

    protected $table = 'finance_transactions_entities';

    protected $fillable = [
        'game_entity_account_id',
        'club_account_id',
        'direction',
        'event_type',
        'event_id',
        'amount',
        'transaction_date',
    ];

    protected function casts(): array
    {
        return [
            'direction' => EntityTransactionDirection::class,
            'event_type' => EntityTransactionType::class,
            'event_id' => 'integer',
            'amount' => 'integer',
            'transaction_date' => 'datetime',
        ];
    }

    public function gameEntityAccount(): BelongsTo
    {
        return $this->belongsTo(GameEntityAccount::class);
    }

    public function clubAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'club_account_id');
    }
}
