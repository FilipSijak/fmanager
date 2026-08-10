<?php

namespace App\Services\CompetitionService\Progression;

use App\Models\ClubCompetitionMovement;
use App\Models\ClubCompetitionQualification;
use App\Models\Competition;
use App\Models\CompetitionQualificationRule;
use App\Models\LeagueTierRule;
use App\Models\Season;
use Illuminate\Support\Facades\DB;

class SeasonProgressionService
{
    public function __construct(
        private readonly DomesticMovementService $domesticMovements,
        private readonly ContinentalQualificationService $continentalQualifications,
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

            $movements = $this->domesticMovements->calculateForSeason($instanceId, $seasonId);
            $qualifications = $this->continentalQualifications->calculateForSeason($instanceId, $seasonId);

            foreach ($movements as $movement) {
                ClubCompetitionMovement::query()->updateOrCreate(
                    [
                        'source_season_id' => $seasonId,
                        'club_id' => $movement['club_id'],
                        'type' => $movement['type'],
                    ],
                    $movement
                );
            }
            foreach ($qualifications as $qualification) {
                ClubCompetitionQualification::query()->updateOrCreate(
                    ['source_season_id' => $seasonId, 'club_id' => $qualification['club_id']],
                    $qualification
                );
            }

            return ['movements' => $movements->count(), 'qualifications' => $qualifications->count()];
        });
    }

    private function assertSourcesFinished(int $instanceId, int $seasonId): void
    {
        $baseIds = LeagueTierRule::query()->where('active', true)->get()
            ->flatMap(fn ($rule) => [$rule->upper_base_competition_id, $rule->lower_base_competition_id]);
        $baseIds = $baseIds->concat(
            CompetitionQualificationRule::query()->where('active', true)->pluck('source_base_competition_id')
        )->unique();

        Competition::query()->forInstance($instanceId)->whereIn('base_competition_id', $baseIds)->get()
            ->each(fn (Competition $competition) => $this->eligibility->assertCompetitionFinished(
                $instanceId, $seasonId, $competition->id
            ));
    }
}
