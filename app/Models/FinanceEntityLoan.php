<?php

namespace App\Models;

use App\FinanceEntityLoanStatus;
use App\FinanceLoanType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceEntityLoan extends Model
{
    use HasFactory;

    protected $fillable = [
        'instance_id',
        'lender_game_entity_account_id',
        'borrower_club_account_id',
        'loan_type',
        'principal',
        'interest_amount',
        'total_amount',
        'installment_count',
        'started_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'principal' => 'integer',
            'interest_amount' => 'integer',
            'total_amount' => 'integer',
            'installment_count' => 'integer',
            'started_at' => 'date',
            'status' => FinanceEntityLoanStatus::class,
            'loan_type' => FinanceLoanType::class,
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    public function lenderGameEntityAccount(): BelongsTo
    {
        return $this->belongsTo(GameEntityAccount::class, 'lender_game_entity_account_id');
    }

    public function borrowerClubAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'borrower_club_account_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(AccountsDebtLinesEntity::class, 'loan_id');
    }
}
