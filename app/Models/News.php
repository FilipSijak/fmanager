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
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function scopeForInstance($query, int $instanceId)
    {
        return $query->where('instance_id', $instanceId);
    }

    public function scopeForSeason($query, int $seasonId)
    {
        return $query->where('season_id', $seasonId);
    }

    public function scopeForClub($query, int $clubId)
    {
        return $query->where('club_id', $clubId);
    }

    public function scopeInboxOrder($query)
    {
        return $query
            ->orderBy('priority')
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

}
