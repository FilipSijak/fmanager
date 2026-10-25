<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGameInstance;
use Database\Factories\GamePlayerStatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One player's performance in one game. Goals conceded, saves and clean sheets are only counted for goalkeepers.
 */
class GamePlayerStat extends Model
{
    /** @use HasFactory<GamePlayerStatFactory> */
    use BelongsToGameInstance, HasFactory;

    /** Per-match tallies, zero unless the player did it. */
    public const COUNTERS = [
        'goals', 'assists', 'shots', 'shots_on_target', 'key_passes',
        'passes_attempted', 'passes_completed', 'tackles_attempted', 'tackles_won', 'interceptions',
        'fouls_committed', 'fouls_won', 'yellow_cards', 'red_cards', 'offsides',
        'saves', 'goals_conceded',
    ];

    public $timestamps = false;

    protected $fillable = [
        'instance_id', 'game_id', 'club_id', 'player_id', 'position', 'started', 'minutes_played',
        'goals', 'assists', 'shots', 'shots_on_target', 'key_passes',
        'passes_attempted', 'passes_completed', 'tackles_attempted', 'tackles_won', 'interceptions',
        'fouls_committed', 'fouls_won', 'yellow_cards', 'red_cards', 'offsides',
        'saves', 'goals_conceded', 'clean_sheet', 'rating',
    ];

    protected function casts(): array
    {
        return [
            'started' => 'boolean',
            'clean_sheet' => 'boolean',
            'rating' => 'float',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
