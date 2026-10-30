<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Club;
use App\Models\Competition;
use App\Services\InstanceService\InstanceService;
use App\Services\PersonService\PersonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InstanceController extends Controller
{
    private InstanceService $instanceService;

    public function __construct(
        InstanceService $instanceService,
    ) {
        $this->instanceService = $instanceService;
    }

    public function setup(Request $request): Response
    {
        $pendingInstance = $request->filled('instance_id')
            ? $request->user()->instances()->whereNull('manager_id')->findOrFail($request->integer('instance_id'))
            : null;
        $leagues = $pendingInstance === null ? collect() : Competition::query()
            ->forInstance($pendingInstance->id)
            ->where('type', 'league')
            ->whereHas('clubs', fn ($query) => $query->where('clubs.instance_id', $pendingInstance->id)->where('competition_season.season_id', $pendingInstance->season_id))
            ->with(['clubs' => fn ($query) => $query->where('clubs.instance_id', $pendingInstance->id)
                ->where('competition_season.season_id', $pendingInstance->season_id)
                ->orderBy('clubs.name')->orderBy('clubs.id')])
            ->orderBy('country_code')->orderBy('name')->orderBy('id')->get();

        return Inertia::render('GameStart', [
            'pending_instance_id' => $pendingInstance?->id,
            'leagues' => $leagues->map(fn (Competition $league): array => [
                'id' => $league->id,
                'name' => $league->name,
                'country_code' => $league->country_code,
                'clubs' => $league->clubs
                    ->map(fn (Club $club): array => ['id' => $club->id, 'name' => $club->name])
                    ->values()->all(),
            ]),
            'instances' => $request->user()->instances()
                ->with('club:id,name')
                ->orderByDesc('id')
                ->get(['id', 'club_id', 'manager_id', 'instance_date'])
                ->map(fn ($instance): array => [
                    'id' => $instance->id,
                    'club_name' => $instance->club?->name,
                    'awaiting_club_selection' => $instance->isAwaitingClubSelection(),
                    'date' => $instance->instance_date,
                ]),
        ]);
    }

    public function takeOver(Request $request, int $instanceId, PersonService $personService): JsonResponse
    {
        $instance = $request->user()->instances()->findOrFail($instanceId);
        abort_if($instance->club_id !== null || $instance->manager_id !== null, 409, 'A club has already been selected for this game.');
        $validated = $request->validate([
            'league_id' => ['required', 'integer', Rule::exists('competitions', 'id')->where('instance_id', $instance->id)->where('type', 'league')],
            'club_id' => ['required', 'integer', Rule::exists('clubs', 'id')->where('instance_id', $instance->id)
                ->whereIn('id', DB::table('competition_season')->where('instance_id', $instance->id)
                    ->where('season_id', $instance->season_id)->where('competition_id', $request->integer('league_id'))->pluck('club_id')->all())],
        ], [
            'league_id.required' => 'Choose a league.',
            'league_id.integer' => 'Choose a valid league.',
            'league_id.exists' => 'Choose a valid league.',
            'club_id.required' => 'Choose a club.',
            'club_id.integer' => 'Choose a club from the selected league.',
            'club_id.exists' => 'Choose a club from the selected league.',
        ]);
        $instance = $this->instanceService->takeOverClub($request->user(), $instance->id, (int) $validated['club_id'], (int) $validated['league_id'], $personService);
        $request->session()->put('active_instance_hash', $instance->instance_hash);

        return ResponseHelper::success($instance->toArray());
    }

    public function select(Request $request, int $instanceId): RedirectResponse
    {
        $instance = $request->user()->instances()->findOrFail($instanceId);
        if ($instance->isAwaitingClubSelection()) {
            return redirect()->route('setup-game', ['instance_id' => $instance->id]);
        }
        $request->session()->put('active_instance_hash', $instance->instance_hash);

        return redirect()->route('start');
    }
}
