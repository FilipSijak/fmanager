<?php

namespace App\Http\Controllers;

use App\Http\Resources\CompetitionTableRowResource;
use App\Models\Competition;
use App\Models\Instance;
use App\Models\Season;
use App\Repositories\Competition\CompetitionReadRepository;
use App\Support\GameContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CompetitionPageController extends Controller
{
    public function __construct(
        private readonly GameContext $gameContext,
        private readonly CompetitionReadRepository $competitions,
    ) {}

    public function index(): Response
    {
        return $this->render();
    }

    public function show(int $competitionId): Response
    {
        $competition = Competition::query()->forInstance($this->gameContext->instanceId())
            ->findOrFail($competitionId);

        return $this->render($competition);
    }

    private function render(?Competition $competition = null): Response
    {
        $instance = Instance::findOrFail($this->gameContext->instanceId());
        $available = $this->competitions->competitions();

        if ($competition === null) {
            $leagueId = DB::table('competition_season AS cs')
                ->join('competitions AS c', 'c.id', '=', 'cs.competition_id')
                ->where('cs.instance_id', $instance->id)->where('c.instance_id', $instance->id)
                ->where('cs.season_id', $this->gameContext->seasonId())
                ->where('cs.club_id', $instance->club_id)->where('c.type', 'league')
                ->orderBy('c.rank')->value('c.id');
            $competition = $available->firstWhere('id', $instance->followed_competition_id)
                ?? $available->firstWhere('id', $leagueId)
                ?? $available->first();
        }

        $isLeague = $competition?->type === 'league';
        $groups = $competition !== null && ! $isLeague
            ? $this->competitions->groupTables($competition->id)->groupBy('group_id')
            : collect();

        return Inertia::render('competitions/Competition', [
            'competitions' => $available,
            'competition' => $competition?->only(['id', 'name', 'type', 'country_code', 'competition_scope', 'groups']),
            'season' => Season::query()->where('instance_id', $instance->id)
                ->find($this->gameContext->seasonId())?->only(['start_date', 'end_date']),
            'clubId' => $instance->club_id,
            'table' => $isLeague ? $this->tableRows($this->competitions->table($competition->id)) : [],
            'groups' => $groups->map(fn (Collection $rows, int|string $id): array => [
                'id' => (int) $id, 'table' => $this->tableRows($rows),
            ])->values(),
            'matches' => $competition === null ? [] : $this->competitions->matches($competition->id),
            'rounds' => $competition === null || $isLeague ? [] : $this->competitions->knockoutRounds($competition->id),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function tableRows(Collection $rows): array
    {
        $rows = $rows->values()->each(function ($row, int $index): void {
            $row->position = $index + 1;
        });

        return CompetitionTableRowResource::collection($rows)->resolve();
    }
}
