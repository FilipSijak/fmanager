<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTrainingRequest;
use App\Services\TrainingService\TrainingManagementService;
use Illuminate\Http\JsonResponse;

class TrainingController extends Controller
{
    public function show(TrainingManagementService $training): JsonResponse
    {
        return ResponseHelper::success($training->overview());
    }

    public function update(UpdateTrainingRequest $request, TrainingManagementService $training): JsonResponse
    {
        $data = $request->validated();
        $training->update(array_map(intval(...), $data['player_ids']), $data['schedule']);

        return ResponseHelper::success();
    }
}
