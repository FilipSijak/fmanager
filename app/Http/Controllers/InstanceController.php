<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\InstanceService\InstanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

    public function startNewGame(Request $request): JsonResponse
    {

        try {
            $instance = $this->instanceService->createNewInstance($request->user());

            if ($request->hasSession()) {
                $request->session()->put('active_instance_hash', $instance->instance_hash);
            }

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

    public function setup(Request $request): Response
    {
        return Inertia::render('GameStart', [
            'instances' => $request->user()->instances()
                ->with('club:id,name')
                ->orderByDesc('id')
                ->get(['id', 'club_id', 'instance_date'])
                ->map(fn ($instance): array => [
                    'id' => $instance->id,
                    'club_name' => $instance->club?->name,
                    'date' => $instance->instance_date,
                ]),
        ]);
    }

    public function select(Request $request, int $instanceId): RedirectResponse
    {
        $instance = $request->user()->instances()->findOrFail($instanceId);
        $request->session()->put('active_instance_hash', $instance->instance_hash);

        return redirect()->route('start');
    }
}
