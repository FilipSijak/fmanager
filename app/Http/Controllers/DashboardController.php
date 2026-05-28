<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\DashboardResource;
use App\Services\DashboardService\DashboardService;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService
    )
    {

    }
    public function index()
    {
        $dashboard = new DashboardResource($this->dashboardService->getDashboard());

        return ResponseHelper::success(
            $dashboard->toArray(request()),
            ResponseHelper::RESPONSE_SUCCESS_CODE
        );
    }
}
