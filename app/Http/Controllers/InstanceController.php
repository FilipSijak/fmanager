<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Instance;
use App\Services\InstanceService\InstanceService;
use Illuminate\Http\JsonResponse;

class InstanceController extends Controller
{
    private InstanceService $instanceService;

    public function __construct(
        InstanceService $instanceService
    )
    {
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
                Instance::find($instance->id)->toArray(),
                ResponseHelper::RESPONSE_SUCCESS_CODE
            );
        } catch (\Exception $exception) {
            return ResponseHelper::error(
                'Failed to create new instance',
                $exception->getMessage(),
                ResponseHelper::RESPONSE_ERROR_CODE
            );
        }
    }
}
