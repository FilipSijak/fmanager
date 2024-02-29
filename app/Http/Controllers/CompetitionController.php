<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompetitionResource;
use App\Models\Competition;
use Illuminate\Support\Facades\DB;

class CompetitionController extends Controller
{
    public function show(Competition $competition)
    {
        return new CompetitionResource($competition);
    }

    public function competitionTable(Competition $competition)
    {


        return response()->json(
            [
                "data" => [],
            ],
            200
        );
    }
}
