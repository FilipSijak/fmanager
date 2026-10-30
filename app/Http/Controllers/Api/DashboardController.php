<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Services\DashboardService\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService
    ) {}

    public function index(): JsonResponse
    {
        $dashboard = new DashboardResource($this->dashboardService->getDashboard());

        return ResponseHelper::success(
            $dashboard->toArray(request()),
            ResponseHelper::RESPONSE_SUCCESS_CODE
        );
    }
}
