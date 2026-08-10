<?php

namespace App\Services\CompetitionService\Progression;

use App\Models\ClubCompetitionProgression;
use App\Models\Competition;
use App\Models\CompetitionProgressionRule;
use App\Models\Season;
use Illuminate\Support\Facades\DB;

class SeasonProgressionService
{
    public function __construct(
        private readonly CompetitionProgressionCalculator $calculator,
        private readonly CompetitionProgressionEligibility $eligibility,
        private readonly ProgressionRuleValidator $validator
    ) {}

    public function finalize(int $seasonId): array
    {
        return DB::transaction(function () use ($seasonId): array {
            $season = Season::query()->lockForUpdate()->findOrFail($seasonId);
            $instanceId = (int) $season->instance_id;
            $this->validator->validate();
            $this->assertSourcesFinished($instanceId, $seasonId);

            $progressions = $this->calculator->calculateForSeason($instanceId, $seasonId);
            ClubCompetitionProgression::query()->where('source_season_id', $seasonId)
                ->where('status', 'pending')->delete();

            foreach ($progressions as $progression) {
                ClubCompetitionProgression::query()->create($progression);
            }

            return [
                'movements' => $progressions->whereIn('progression_type', ['promotion', 'relegation'])->count(),
                'qualifications' => $progressions->where('progression_type', 'continental')->count(),
            ];
        });
    }

    private function assertSourcesFinished(int $instanceId, int $seasonId): void
    {
        $sourceBaseIds = CompetitionProgressionRule::query()->where('active', true)
            ->pluck('source_base_competition_id')->unique();

        Competition::query()->forInstance($instanceId)->whereIn('base_competition_id', $sourceBaseIds)->get()
            ->each(fn (Competition $competition) => $this->eligibility->assertCompetitionFinished(
                $instanceId, $seasonId, $competition->id
            ));
    }
}
