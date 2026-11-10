<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\RenewPlayerContractRequest;
use App\Services\ContractService\ContractService;
use Illuminate\Http\JsonResponse;

class PlayerContractController extends Controller
{
    public function __construct(private readonly ContractService $contracts) {}

    public function store(RenewPlayerContractRequest $request, int $playerId): JsonResponse
    {
        return ResponseHelper::success($this->contracts->renewPlayerContract($playerId, $request->validated()));
    }
}
