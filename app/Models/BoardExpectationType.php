<?php

namespace App\Models;

use Database\Factories\BoardExpectationTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BoardExpectationType extends Model
{
    /** @use HasFactory<BoardExpectationTypeFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['description', 'rating', 'tolerance'];

    protected $casts = ['rating' => 'integer', 'tolerance' => 'integer'];
}
