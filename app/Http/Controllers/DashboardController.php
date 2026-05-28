<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
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
        return ResponseHelper::success(
            $this->dashboardService->getDashboard(),
            ResponseHelper::RESPONSE_SUCCESS_CODE
        );
    }
}
