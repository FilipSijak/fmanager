<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountsDebtLinesEntity extends Model
{
    use HasFactory;

    protected $table = 'accounts_debt_lines_entities';

    public $timestamps = false;

    protected $fillable = [
        'loan_id',
        'game_entity_account_id',
        'club_account_id',
        'amount',
        'created_at',
        'due_date',
        'installment_number',
        'paid_at',
        'transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'created_at' => 'date',
            'due_date' => 'date',
            'installment_number' => 'integer',
            'paid_at' => 'datetime',
            'transaction_id' => 'integer',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(FinanceEntityLoan::class, 'loan_id');
    }

    public function gameEntityAccount(): BelongsTo
    {
        return $this->belongsTo(GameEntityAccount::class);
    }

    public function clubAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'club_account_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinanceTransactionEntity::class, 'transaction_id');
    }
}
