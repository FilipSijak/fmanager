<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferStatus extends Model
{
    use HasFactory;

    public $table = 'transfer_status';
    public $timestamps = false;
}
