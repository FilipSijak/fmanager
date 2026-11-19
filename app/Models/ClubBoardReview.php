<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGameInstance;
use Database\Factories\ClubBoardReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubBoardReview extends Model
{
    /** @use HasFactory<ClubBoardReviewFactory> */
    use BelongsToGameInstance, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'instance_id', 'club_season_expectation_id', 'staff_coaching_id', 'reviewed_on',
        'position', 'played', 'effective_tolerance', 'outcome', 'reason',
    ];

    protected $casts = [
        'reviewed_on' => 'immutable_date', 'position' => 'integer', 'played' => 'integer',
        'effective_tolerance' => 'integer',
    ];

    public function expectation(): BelongsTo
    {
        return $this->belongsTo(ClubSeasonExpectation::class, 'club_season_expectation_id');
    }
}
