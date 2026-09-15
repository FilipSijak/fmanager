<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameEntityAccount extends Model
{
    use HasFactory;

    protected $table = 'accounts_game_entities';

    protected $fillable = [
        'game_entity_id',
        'instance_id',
        'balance',
        'future_balance',
        'allowed_debt',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'future_balance' => 'integer',
            'allowed_debt' => 'integer',
        ];
    }

    public function gameEntity(): BelongsTo
    {
        return $this->belongsTo(GameEntity::class);
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinanceTransactionEntity::class);
    }

    public function debtLines(): HasMany
    {
        return $this->hasMany(AccountsDebtLinesEntity::class);
    }
}
