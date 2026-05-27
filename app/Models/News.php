<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    protected $table = 'news';

    protected $fillable = [
        'instance_id',
        'season_id',
        'club_id',
        'competition_id',
        'title',
        'content',
        'type',
        'priority',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime'
    ];
}
