<?php

namespace App\Http\Controllers;

use App\Repositories\GameRepository;
use Inertia\Inertia;
use Inertia\Response;

class MatchPageController extends Controller
{
    public function show(int $gameId, GameRepository $games): Response
    {
        $game = $games->details($gameId);
        abort_if($game === null, 404);
        $summary = $game['match_summary'] === null ? [] : json_decode($game['match_summary'], true, flags: JSON_THROW_ON_ERROR);
        $events = collect(is_array($summary) ? ($summary['events'] ?? []) : [])
            ->filter(fn ($event): bool => is_array($event) && isset($event['minute'], $event['type'])
                && is_numeric($event['minute']) && is_string($event['type']))
            ->map(fn (array $event, int|string $eventIndex): array => [
                'id' => $game['id'].':'.$eventIndex,
                'minute' => (int) $event['minute'], 'type' => $event['type'],
                'club_id' => isset($event['club_id']) ? (int) $event['club_id'] : null,
            ])->sortBy('minute')->values();
        unset($game['match_summary']);

        return Inertia::render('matches/Match', ['match' => $game, 'events' => $events]);
    }
}
