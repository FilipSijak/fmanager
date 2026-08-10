<?php

namespace App\Services\CompetitionService\Progression;

use App\Models\CompetitionProgressionRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class ProgressionRuleValidator
{
    public function validate(): void
    {
        $baseCompetitions = DB::table('base_competitions')->get()->keyBy('id');
        $rules = CompetitionProgressionRule::query()->where('active', true)->get();

        foreach ($rules as $rule) {
            $source = $baseCompetitions->get($rule->source_base_competition_id);
            $target = $baseCompetitions->get($rule->target_base_competition_id);
            if (! $source || ! $target) {
                throw new LogicException('Progression rules require existing source and target competitions.');
            }
            if (! in_array($rule->progression_type, ['promotion', 'relegation', 'continental'], true)) {
                throw new LogicException('Progression type is invalid.');
            }
            if (! in_array($rule->selector_type, ['position_range', 'bottom_positions', 'competition_winner'], true)) {
                throw new LogicException('Progression selector type is invalid.');
            }
            if ($rule->progression_type === 'continental'
                && ($target->type !== 'tournament' || $target->competition_scope !== 'continental'
                    || ! $target->continent || ! in_array((int) $target->continental_tier, [1, 2, 3], true))) {
                throw new LogicException(
                    'Continental progression must target a tier-one, tier-two or tier-three continental tournament.'
                );
            }
            if (in_array($rule->progression_type, ['promotion', 'relegation'], true)
                && ($source->type !== 'league' || $target->type !== 'league'
                    || $source->country_code !== $target->country_code)) {
                throw new LogicException('Promotion and relegation must connect leagues in the same country.');
            }
            $this->validateSelector($rule, (int) $source->clubs_number);
        }

        $this->validateDomesticPairs($rules);
        $this->validateContinentalTiers($baseCompetitions);
    }

    private function validateSelector(CompetitionProgressionRule $rule, int $clubCount): void
    {
        if ($rule->selector_type === 'position_range'
            && (! $rule->position_from || ! $rule->position_to
                || $rule->position_from > $rule->position_to || $rule->position_to > $clubCount)) {
            throw new LogicException('Progression position range is invalid.');
        }
        if ($rule->selector_type === 'bottom_positions'
            && (! $rule->places || $rule->places > $clubCount)) {
            throw new LogicException('Bottom-position progression places are invalid.');
        }
        if (! in_array($rule->duplicate_policy, ['next_league_position', 'discard'], true)) {
            throw new LogicException('Progression duplicate policy is invalid.');
        }
    }

    private function validateContinentalTiers(Collection $baseCompetitions): void
    {
        $continents = $baseCompetitions->where('competition_scope', 'continental')->groupBy('continent');
        foreach ($continents as $continent => $competitions) {
            $tiers = $competitions->pluck('continental_tier')
                ->map(fn ($tier): int => (int) $tier)->sort()->values()->all();
            if ($tiers !== [1, 2, 3]) {
                throw new LogicException(
                    "Continental competitions for {$continent} must define tiers one, two and three."
                );
            }
        }
    }

    private function validateDomesticPairs(Collection $rules): void
    {
        foreach ($rules->whereIn('progression_type', ['promotion', 'relegation']) as $rule) {
            $oppositeType = $rule->progression_type === 'promotion' ? 'relegation' : 'promotion';
            $opposite = $rules->first(fn (CompetitionProgressionRule $candidate): bool => $candidate->progression_type === $oppositeType
                && $candidate->source_base_competition_id === $rule->target_base_competition_id
                && $candidate->target_base_competition_id === $rule->source_base_competition_id
            );
            if (! $opposite || $this->places($rule) !== $this->places($opposite)) {
                throw new LogicException('Promotion and relegation rules must have a balanced reverse rule.');
            }
        }
    }

    private function places(CompetitionProgressionRule $rule): int
    {
        return $rule->selector_type === 'bottom_positions'
            ? (int) $rule->places
            : (int) $rule->position_to - (int) $rule->position_from + 1;
    }
}
