<?php

namespace App\Http\Controllers;

use App\Helpers\HeaderInfoTrait;
use Illuminate\Http\Request;

class CoreController extends Controller
{
    use HeaderInfoTrait;

    protected int $seasonId;
    protected int $instanceId;

    public function __construct(Request $request)
    {
        $this->seasonId = $this->getSeasonId($request);
        $this->instanceId = $this->getInstanceId($request);
    }
}
