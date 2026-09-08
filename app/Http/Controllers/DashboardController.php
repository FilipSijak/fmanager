<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\DashboardResource;
use App\Services\DashboardService\DashboardService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

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

    public function show(): Response
    {
        $dashboard = (new DashboardResource($this->dashboardService->getDashboard()))
            ->resolve(request());

        return Inertia::render('Dashboard', [
            'dashboard' => $dashboard,
        ]);
    }
}
