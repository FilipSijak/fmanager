<?php

namespace App\Models;

use App\Services\CommercialService\Domain\TicketType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchdaySettlement extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['ticket_type' => TicketType::class, 'season_ticket_attendance' => 'integer', 'paying_attendance' => 'integer', 'attendance' => 'integer', 'ticket_price' => 'integer', 'gate_receipts' => 'integer',
            'commercial_income' => 'integer', 'operating_costs' => 'integer', 'settled_on' => 'date'];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
