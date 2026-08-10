<?php

namespace App\Services\CompetitionService\Progression;

use App\Models\Competition;
use App\Models\LeagueTierRule;
use Illuminate\Support\Collection;
use LogicException;

class DomesticMovementService
{
    public function __construct(private readonly FinalLeagueTableService $tables) {}

    public function calculateForSeason(int $instanceId, int $seasonId): Collection
    {
        return LeagueTierRule::query()->where('active', true)->get()
            ->flatMap(fn (LeagueTierRule $rule) => $this->calculateRule($rule, $instanceId, $seasonId))
            ->values();
    }

    public function previewForCompetition(Competition $competition, int $seasonId): Collection
    {
        return LeagueTierRule::query()
            ->where('active', true)
            ->where(function ($query) use ($competition): void {
                $query->where('upper_base_competition_id', $competition->base_competition_id)
                    ->orWhere('lower_base_competition_id', $competition->base_competition_id);
            })
            ->get()
            ->flatMap(fn (LeagueTierRule $rule) => $this->calculateRule($rule, $competition->instance_id, $seasonId))
            ->filter(fn (array $movement) => $movement['from_competition_id'] === $competition->id
                || $movement['to_competition_id'] === $competition->id)
            ->values();
    }

    public function calculateRule(LeagueTierRule $rule, int $instanceId, int $seasonId): Collection
    {
        $upper = $this->competition($instanceId, $rule->upper_base_competition_id);
        $lower = $this->competition($instanceId, $rule->lower_base_competition_id);
        if ($upper->type !== 'league' || $lower->type !== 'league') {
            throw new LogicException('Domestic movement rules can only connect league competitions.');
        }

        $places = (int) $rule->automatic_movement_places;
        $upperTable = $this->tables->get($instanceId, $seasonId, $upper->id);
        $lowerTable = $this->tables->get($instanceId, $seasonId, $lower->id);
        if ($places < 1) {
            return collect();
        }
        if ($upperTable->count() < $places || $lowerTable->count() < $places) {
            throw new LogicException('Movement places exceed the available league standings.');
        }

        $relegated = $upperTable->take(-$places)->values()->map(fn ($row) => $this->movement(
            $instanceId, $seasonId, $row, $upper->id, $lower->id, 'relegation'
        ));
        $promoted = $lowerTable->take($places)->values()->map(fn ($row) => $this->movement(
            $instanceId, $seasonId, $row, $lower->id, $upper->id, 'promotion'
        ));

        return $relegated->concat($promoted)->values();
    }

    private function competition(int $instanceId, int $baseCompetitionId): Competition
    {
        return Competition::query()->forInstance($instanceId)
            ->where('base_competition_id', $baseCompetitionId)->firstOrFail();
    }

    private function movement(int $instanceId, int $seasonId, object $row, int $from, int $to, string $type): array
    {
        return [
            'instance_id' => $instanceId,
            'source_season_id' => $seasonId,
            'club_id' => (int) $row->club_id,
            'from_competition_id' => $from,
            'to_competition_id' => $to,
            'type' => $type,
            'source_position' => (int) $row->position,
            'status' => 'pending',
        ];
    }
}
