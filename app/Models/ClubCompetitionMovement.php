<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ClubCompetitionMovement extends Model { protected $guarded = []; protected $casts = ['applied_at' => 'datetime']; }
