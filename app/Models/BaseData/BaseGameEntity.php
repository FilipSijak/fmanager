<?php

namespace App\Models\BaseData;

use App\GameEntityType;
use Database\Factories\BaseGameEntityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BaseGameEntity extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['type', 'name', 'rank', 'starting_balance'];

    protected function casts(): array
    {
        return ['type' => GameEntityType::class, 'rank' => 'integer', 'starting_balance' => 'integer'];
    }

    protected static function newFactory(): BaseGameEntityFactory
    {
        return BaseGameEntityFactory::new();
    }
}
