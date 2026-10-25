<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGameInstance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The user's match while it is being played, stored between the requests that play it a few minutes at a time.
 */
class LiveMatch extends Model
{
    use BelongsToGameInstance;

    protected $fillable = ['instance_id', 'game_id', 'state'];

    protected function casts(): array
    {
        return ['state' => 'array'];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
