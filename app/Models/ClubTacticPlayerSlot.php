<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubTacticPlayerSlot extends Model
{
    protected $table = 'club_tactic_players_slots';

    protected $fillable = [
        'instance_id',
        'club_id',
        'club_tactic_id',
        'player_id',
        'slot',
        'position',
    ];

    public function clubTactic(): BelongsTo
    {
        return $this->belongsTo(ClubTactic::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
