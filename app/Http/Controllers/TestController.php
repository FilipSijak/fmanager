<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Services\InstanceService\CreateInstance;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function index()
    {
        $createInstance = new CreateInstance();
        $createInstance->storeGame();

        dd(Club::all());

        dd(Carbon::now()->format('Y-m-d H:i'));
        $randomYear = random_int(1980, 2007);

        $time = Carbon::createFromDate($randomYear, 11, 22)->format('Y-m-d -h-m-s');
        dd($time);
    }
}
