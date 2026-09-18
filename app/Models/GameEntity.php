<?php

namespace App\Models;

use App\GameEntityType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GameEntity extends Model
{
    use HasFactory;

    protected $fillable = ['instance_id', 'competition_id', 'type', 'name'];

    protected function casts(): array
    {
        return ['type' => GameEntityType::class];
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }

    public function account(): HasOne
    {
        return $this->hasOne(GameEntityAccount::class);
    }
}
