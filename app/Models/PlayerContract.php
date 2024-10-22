<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerContract extends Model
{
    use HasFactory;

    public $table = 'players_contracts';
    public $timestamps = false;

    protected $fillable = ['salary', 'appearance', 'clean_sheet', 'goal',
                           'assist', 'league', 'promotion', 'cup', 'el',
                           'cl', 'pc_promotion_salary_raise', 'pc_demotion_salary_cut'];
}
