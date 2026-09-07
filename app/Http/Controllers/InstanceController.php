<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\InstanceService\InstanceService;
use Illuminate\Http\JsonResponse;

class InstanceController extends Controller
{
    private InstanceService $instanceService;

    public function __construct(
        InstanceService $instanceService
    ) {
        $this->instanceService = $instanceService;
    }

    public function nextDay(): void
    {
        $this->instanceService->nextDay();
    }

    public function startNewGame(): JsonResponse
    {

        try {
            $instance = $this->instanceService->createNewInstance();

            return ResponseHelper::success(
                $instance->toArray(),
                201
            );
        } catch (\Throwable $exception) {
            report($exception);

            return ResponseHelper::error(
                'Failed to create new instance',
                '',
                ResponseHelper::RESPONSE_ERROR_CODE
            );
        }
    }
}
