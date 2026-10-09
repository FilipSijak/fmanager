<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\BaseData\BaseClubs;
use App\Models\BaseData\BaseCompetitions;
use App\Services\InstanceService\InstanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $validated = $request->validate([
            'league_id' => ['required', 'integer', Rule::exists('base_competitions', 'id')->where('type', 'league')],
            'club_id' => ['required', 'integer', Rule::exists('base_clubs', 'id')->where('competition_id', $request->integer('league_id'))],
        ], [
            'league_id.required' => 'Choose a league.',
            'league_id.integer' => 'Choose a valid league.',
            'league_id.exists' => 'Choose a valid league.',
            'club_id.required' => 'Choose a club.',
            'club_id.integer' => 'Choose a club from the selected league.',
            'club_id.exists' => 'Choose a club from the selected league.',
        ]);

        try {
            $instance = $this->instanceService->createNewInstance($request->user(), (int) $validated['club_id']);

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
        $clubsByLeague = BaseClubs::query()
            ->orderBy('name')->orderBy('id')
            ->get(['id', 'name', 'competition_id'])
            ->groupBy('competition_id');

        return Inertia::render('GameStart', [
            'leagues' => BaseCompetitions::query()
                ->where('type', 'league')
                ->whereIn('id', $clubsByLeague->keys())
                ->orderBy('country_code')->orderBy('name')->orderBy('id')
                ->get(['id', 'name', 'country_code'])
                ->map(fn (BaseCompetitions $league): array => [
                    'id' => $league->id,
                    'name' => $league->name,
                    'country_code' => $league->country_code,
                    'clubs' => $clubsByLeague->get($league->id)
                        ->map(fn (BaseClubs $club): array => ['id' => $club->id, 'name' => $club->name])
                        ->values()->all(),
                ]),
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
