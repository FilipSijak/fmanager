<?php

namespace App\Http\Controllers;

use App\Http\Resources\DashboardResource;
use App\Services\DashboardService\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService
    ) {}

    public function show(): Response
    {
        $dashboard = (new DashboardResource($this->dashboardService->getDashboard()))
            ->resolve(request());

        return Inertia::render('Dashboard', [
            'dashboard' => $dashboard,
        ]);
    }
}
