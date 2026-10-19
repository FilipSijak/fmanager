<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Instance;
use App\Models\ManagerJobApplication;
use App\Models\StaffCoaching;
use App\Services\ManagerJobService\ManagerJobService;
use App\Support\GameContext;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ManagerJobController extends Controller
{
    public function __construct(
        private readonly ManagerJobService $managerJobService,
        private readonly GameContext $gameContext,
    ) {}

    public function index(): Response
    {
        $instance = Instance::query()->findOrFail($this->gameContext->instanceId());
        $leagueIdsByClub = $this->managerJobService->leagueIdsByClub($instance);
        $leagueNames = Competition::query()->whereKey(array_unique($leagueIdsByClub))->pluck('name', 'id');
        $appliedClubIds = ManagerJobApplication::query()
            ->forInstance($instance->id)
            ->pending()
            ->where('staff_coaching_id', $instance->manager_id)
            ->pluck('club_id')
            ->all();

        return Inertia::render('Jobs', [
            'is_unemployed' => $instance->isManagerUnemployed(),
            'reputation' => StaffCoaching::query()->whereKey($instance->manager_id)->value('reputation'),
            'reputation_tolerance' => ManagerJobService::REPUTATION_TOLERANCE,
            'vacancies' => $this->managerJobService->vacantClubs($instance)
                ->map(fn (Club $club): array => [
                    'club_id' => $club->id,
                    'club_name' => $club->name,
                    'league_name' => $leagueNames[$leagueIdsByClub[$club->id] ?? null] ?? null,
                    'required_reputation' => $club->managerReputationTarget(),
                    'applied' => in_array($club->id, $appliedClubIds, true),
                ])
                ->values(),
        ]);
    }

    public function apply(int $clubId): RedirectResponse
    {
        try {
            $this->managerJobService->apply(Instance::query()->findOrFail($this->gameContext->instanceId()), $clubId);
        } catch (DomainException $exception) {
            return back()->withErrors(['application' => $exception->getMessage()]);
        }

        return back();
    }
}
