<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchdaySettlement extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['attendance' => 'integer', 'ticket_price' => 'integer', 'gate_receipts' => 'integer',
            'commercial_income' => 'integer', 'operating_costs' => 'integer', 'settled_on' => 'date'];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
