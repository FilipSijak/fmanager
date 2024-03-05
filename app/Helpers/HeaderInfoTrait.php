<?php

namespace App\Helpers;

use Illuminate\Http\Request;

trait HeaderInfoTrait
{
    public function getInstanceId(Request $request): int
    {
        return (int)$request->header('instanceId');
    }

    public function getSeasonId(Request $request): int
    {
        return (int)$request->header('seasonId');
    }
}
