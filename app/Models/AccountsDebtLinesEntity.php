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
        'game_entity_account_id',
        'club_account_id',
        'amount',
        'created_at',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'created_at' => 'date',
            'due_date' => 'date',
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
