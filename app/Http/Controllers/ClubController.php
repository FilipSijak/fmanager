<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClubResource;
use App\Models\Club;

class ClubController extends Controller
{
    public function show(Club $club)
    {
        return new ClubResource($club);
    }
}
