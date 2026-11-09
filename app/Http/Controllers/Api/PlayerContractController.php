<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\RenewPlayerContractRequest;
use App\Services\PersonService\PersonService;
use Illuminate\Http\JsonResponse;

class PlayerContractController extends Controller
{
    public function __construct(private readonly PersonService $persons) {}

    public function store(RenewPlayerContractRequest $request, int $playerId): JsonResponse
    {
        return ResponseHelper::success($this->persons->renewPlayerContract($playerId, $request->validated()));
    }
}
