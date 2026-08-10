<?php

namespace App\Services\CompetitionService\Progression;

use App\Models\CompetitionQualificationRule;
use App\Models\LeagueTierRule;
use Illuminate\Support\Facades\DB;
use LogicException;

class ProgressionRuleValidator
{
    public function validate(): void
    {
        $base = DB::table('base_competitions')->get()->keyBy('id');
        $graph = [];
        foreach (LeagueTierRule::query()->where('active', true)->get() as $rule) {
            $upper = $base->get($rule->upper_base_competition_id);
            $lower = $base->get($rule->lower_base_competition_id);
            if (! $upper || ! $lower || $upper->type !== 'league' || $lower->type !== 'league') {
                throw new LogicException('League tier rules must connect two existing leagues.');
            }
            if ($upper->country_code !== $lower->country_code) {
                throw new LogicException('League tier rules cannot connect different countries.');
            }
            if ($rule->automatic_movement_places > min($upper->clubs_number, $lower->clubs_number)) {
                throw new LogicException('Automatic movement places exceed competition capacity.');
            }
            $graph[(int) $rule->upper_base_competition_id][] = (int) $rule->lower_base_competition_id;
        }
        $this->assertAcyclic($graph);

        foreach (CompetitionQualificationRule::query()->where('active', true)->get() as $rule) {
            $source = $base->get($rule->source_base_competition_id);
            $target = $base->get($rule->target_base_competition_id);
            if (! $source || ! $target || $target->type !== 'tournament') {
                throw new LogicException('Qualification rules require an existing source and tournament target.');
            }
            if ($rule->selector_type === 'league_position'
                && ($source->type !== 'league' || ! $rule->position_from || ! $rule->position_to
                    || $rule->position_from > $rule->position_to || $rule->position_to > $source->clubs_number)) {
                throw new LogicException('Qualification league positions are invalid.');
            }
            if (! in_array($rule->selector_type, ['league_position', 'competition_winner'], true)) {
                throw new LogicException('Qualification selector type is invalid.');
            }
            if (! in_array($rule->duplicate_policy, ['next_league_position', 'discard'], true)) {
                throw new LogicException('Qualification duplicate policy is invalid.');
            }
        }
    }

    private function assertAcyclic(array $graph): void
    {
        $visiting = [];
        $visited = [];
        $visit = function (int $node) use (&$visit, &$visiting, &$visited, $graph): void {
            if (isset($visiting[$node])) {
                throw new LogicException('League hierarchy contains a cycle.');
            }
            if (isset($visited[$node])) {
                return;
            }
            $visiting[$node] = true;
            foreach ($graph[$node] ?? [] as $child) {
                $visit($child);
            }
            unset($visiting[$node]);
            $visited[$node] = true;
        };
        foreach (array_keys($graph) as $node) {
            $visit((int) $node);
        }
    }
}
