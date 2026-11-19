<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGameInstance;
use Database\Factories\ClubSeasonExpectationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class ClubSeasonExpectation extends Model
{
    /** @use HasFactory<ClubSeasonExpectationFactory> */
    use BelongsToGameInstance, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'instance_id', 'season_id', 'club_id', 'competition_id', 'board_expectation_type_id',
        'description', 'rating', 'tolerance', 'expectation_score', 'target_position', 'league_size',
        'budget', 'transfer_budget', 'team_quality', 'club_rank', 'assigned_on',
    ];

    protected $casts = [
        'rating' => 'integer', 'tolerance' => 'integer', 'expectation_score' => 'integer',
        'target_position' => 'integer', 'league_size' => 'integer', 'budget' => 'integer',
        'transfer_budget' => 'integer', 'team_quality' => 'float', 'club_rank' => 'integer',
        'assigned_on' => 'immutable_date',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(BoardExpectationType::class, 'board_expectation_type_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function acceptsLeaguePosition(int $position): bool
    {
        if ($this->target_position === null || $this->league_size === null) {
            throw new InvalidArgumentException('This expectation has no league position target.');
        }

        if ($position < 1 || $position > $this->league_size) {
            throw new InvalidArgumentException('The finishing position must belong to this league.');
        }

        return $position <= min($this->league_size, $this->target_position + $this->tolerance);
    }
}
