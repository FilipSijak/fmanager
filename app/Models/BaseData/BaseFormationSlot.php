<?php

namespace App\Models\BaseData;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaseFormationSlot extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['base_formation_id', 'slot', 'position', 'x', 'y', 'has_arrow'];

    protected function casts(): array
    {
        return ['x' => 'integer', 'y' => 'integer', 'has_arrow' => 'boolean'];
    }

    public function formation(): BelongsTo
    {
        return $this->belongsTo(BaseFormation::class, 'base_formation_id');
    }
}
