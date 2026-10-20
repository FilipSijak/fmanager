<?php

namespace App\Http\Controllers;

use App\Http\Requests\BrowseSeasonRequest;
use App\Http\Resources\CompetitionTableRowResource;
use App\Models\ClubCompetitionProgression;
use App\Models\Competition;
use App\Models\Instance;
use App\Models\Season;
use App\Repositories\Competition\CompetitionReadRepository;
use App\Services\CompetitionService\Progression\CompetitionProgressionCalculator;
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
        private readonly CompetitionProgressionCalculator $progressionCalculator,
    ) {}

    public function index(BrowseSeasonRequest $request): Response
    {
        return $this->render($request);
    }

    public function show(BrowseSeasonRequest $request, int $competitionId): Response
    {
        $competition = Competition::query()->forInstance($this->gameContext->instanceId())
            ->findOrFail($competitionId);

        return $this->render($request, $competition);
    }

    private function render(BrowseSeasonRequest $request, ?Competition $competition = null): Response
    {
        $instance = Instance::findOrFail($this->gameContext->instanceId());
        $seasonId = $request->filled('season_id') ? $request->integer('season_id') : $this->gameContext->seasonId();
        $season = Season::query()->where('instance_id', $instance->id)->findOrFail($seasonId);
        $historical = $season->id !== $this->gameContext->seasonId();
        $available = $this->competitions->competitions();

        if ($competition === null) {
            $leagueId = DB::table('competition_season AS cs')
                ->join('competitions AS c', 'c.id', '=', 'cs.competition_id')
                ->where('cs.instance_id', $instance->id)->where('c.instance_id', $instance->id)
                ->where('cs.season_id', $season->id)
                ->where('cs.club_id', $instance->club_id)->where('c.type', 'league')
                ->orderBy('c.rank')->value('c.id');
            $competition = $available->firstWhere('id', $instance->followed_competition_id)
                ?? $available->firstWhere('id', $leagueId)
                ?? $available->first();
        }

        $isLeague = $competition?->type === 'league';
        $groups = $competition !== null && ! $isLeague
            ? $this->competitions->groupTables($competition->id, $season->id)->groupBy('group_id')
            : collect();
        $progressions = $isLeague
            ? ($historical
                ? ClubCompetitionProgression::query()->where('instance_id', $instance->id)
                    ->where('source_season_id', $season->id)->get()->toBase()->map(fn ($progression): array => $progression->toArray())
                : $this->progressionCalculator->calculateForSeason($instance->id, $season->id))
                ->filter(fn (array $progression): bool => $progression['progression_type'] === 'continental'
                    || $progression['source_competition_id'] === $competition->id)
                ->map(fn (array $progression): array => [
                    'club_id' => $progression['club_id'],
                    'type' => $progression['progression_type'],
                    'target_name' => $available->firstWhere('id', $progression['target_competition_id'])?->name,
                    'entry_stage' => $progression['entry_stage'],
                ])->groupBy('club_id')
            : collect();

        return Inertia::render('competitions/Competition', [
            'competitions' => $available,
            'competition' => $competition?->only(['id', 'name', 'type', 'country_code', 'competition_scope', 'groups']),
            'season' => $season->only(['id', 'start_date', 'end_date']),
            'seasons' => Season::query()->where('instance_id', $instance->id)->orderByDesc('start_date')->orderByDesc('id')
                ->get(['id', 'start_date', 'end_date']),
            'currentSeasonId' => $this->gameContext->seasonId(),
            'clubId' => $instance->club_id,
            'table' => $isLeague ? $this->tableRows($this->competitions->table($competition->id, $season->id), $progressions) : [],
            'groups' => $groups->map(fn (Collection $rows, int|string $id): array => [
                'id' => (int) $id, 'table' => $this->tableRows($rows, groupStage: true),
            ])->values(),
            'matches' => $competition === null ? [] : $this->competitions->matches($competition->id, $season->id),
            'rounds' => $competition === null || $isLeague ? [] : $this->competitions->knockoutRounds($competition->id, $season->id),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function tableRows(Collection $rows, ?Collection $progressions = null, bool $groupStage = false): array
    {
        $rows = $rows->values()->each(function ($row, int $index): void {
            $row->position = $index + 1;
        });

        return collect(CompetitionTableRowResource::collection($rows)->resolve())
            ->map(function (array $row) use ($progressions, $groupStage): array {
                $row['outcomes'] = $groupStage && $row['position'] <= 2
                    ? [['type' => 'knockout', 'target_name' => 'Knockout phase', 'entry_stage' => null]]
                    : ($progressions?->get($row['club_id'], collect())->values()->all() ?? []);

                return $row;
            })->all();
    }
}
