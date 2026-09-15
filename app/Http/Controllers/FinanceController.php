<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\ClubFinancialSummaryResource;
use App\Services\FinanceService\FinanceService;
use Illuminate\Http\JsonResponse;

class FinanceController extends Controller
{
    public function __construct(
        private readonly FinanceService $financeService,
    ) {}

    public function show(): JsonResponse
    {
        $finances = $this->financeService->getClubFinances();

        return ResponseHelper::success(
            $finances === null
                ? []
                : (new ClubFinancialSummaryResource($finances))->toArray(request()),
        );
    }
}
