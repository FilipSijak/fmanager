<?php

namespace App\Http\Controllers;

use App\Http\Requests\BrowseSeasonRequest;
use App\Models\Player;
use App\Models\Season;
use App\Repositories\ClubRepository;
use App\Repositories\Competition\CompetitionReadRepository;
use App\Support\GameContext;
use Inertia\Inertia;
use Inertia\Response;

class ClubPageController extends Controller
{
    public function show(
        BrowseSeasonRequest $request,
        int $clubId,
        ClubRepository $clubs,
        CompetitionReadRepository $competitions,
        GameContext $context,
    ): Response {
        $club = $clubs->findOrFail($clubId);
        $seasonId = $request->filled('season_id') ? $request->integer('season_id') : $context->seasonId();
        $season = Season::query()->where('instance_id', $context->instanceId())->findOrFail($seasonId);
        $stadium = $club->stadium?->instance_id === $context->instanceId() ? $club->stadium : null;

        return Inertia::render('clubs/Club', [
            'club' => [
                ...$club->only(['id', 'name', 'country_code', 'rank', 'rank_academy', 'rank_training']),
                'stadium' => $stadium?->only(['id', 'name', 'active_capacity', 'capacity']),
            ],
            'season' => $season->only(['id', 'start_date', 'end_date']),
            'seasons' => Season::query()->where('instance_id', $context->instanceId())->orderByDesc('start_date')->orderByDesc('id')
                ->get(['id', 'start_date', 'end_date']),
            'competitions' => $clubs->competitionMemberships($club->id, $season->id),
            'matches' => $competitions->matches(null, $season->id, $club->id),
            'squad' => $clubs->getSquadByPosition($club->id)->map(fn (Player $player): array => [
                'id' => $player->id, 'name' => trim($player->person->first_name.' '.$player->person->last_name),
                'position' => $player->position, 'country_code' => $player->person->country_code,
            ]),
        ]);
    }
}
