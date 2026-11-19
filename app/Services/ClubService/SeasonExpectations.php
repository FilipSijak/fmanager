<?php

namespace App\Services\ClubService;

use App\Models\BoardExpectationType;
use App\Models\Club;
use App\Models\ClubSeasonExpectation;
use App\Models\Instance;
use App\Models\Player;
use App\Repositories\ClubRepository;
use App\Services\ClubService\Domain\BoardExpectationCalculator;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SeasonExpectations
{
    public function __construct(
        private readonly ClubRepository $clubs,
        private readonly BoardExpectationCalculator $calculator,
    ) {}

    public function process(Instance $instance): void
    {
        DB::transaction(function () use ($instance): void {
            $season = $this->clubs->seasonForExpectations((int) $instance->id, (int) $instance->season_id);
            $clubs = $this->clubs->clubsForExpectations((int) $instance->id)->keyBy('id');
            if ($clubs->isEmpty()) {
                return;
            }

            $types = BoardExpectationType::query()->orderBy('rating')->get();
            if ($types->isEmpty()) {
                throw new DomainException('Board expectation types must be configured before assigning season expectations.');
            }

            $existing = ClubSeasonExpectation::query()->forInstance($instance->id)
                ->where('season_id', $season->id)->pluck('club_id')->all();
            $squads = $this->clubs->playersForExpectations((int) $instance->id)
                ->groupBy(fn (Player $player): int => (int) ($player->loan_club_id ?? $player->club_id));
            $inputs = $clubs->map(fn (Club $club): array => [
                'budget' => (int) ($club->account?->balance ?? 0),
                'transfer_budget' => (int) ($club->account?->transfer_budget ?? 0),
                'team_quality' => $this->teamQuality($squads->get($club->id, collect())),
                'club_rank' => (int) $club->rank,
            ]);
            $memberships = $this->clubs->leagueMembershipsForExpectations((int) $instance->id, (int) $season->id)
                ->filter(fn (object $membership): bool => $clubs->has($membership->club_id))
                ->unique('club_id');
            $leagues = $memberships->groupBy('competition_id');
            $leagueByClub = $memberships->keyBy('club_id');

            foreach ($clubs as $club) {
                if (in_array($club->id, $existing, true)) {
                    continue;
                }

                $competitionId = $leagueByClub->get($club->id)?->competition_id;
                $peerIds = $competitionId === null
                    ? $clubs->where('country_code', $club->country_code)->keys()->all()
                    : $leagues->get($competitionId)->pluck('club_id')->all();
                $clubInputs = $inputs->get($club->id);
                $score = $this->calculator->score($clubInputs, $inputs->only($peerIds)->values()->all());
                $type = $types->sortBy(fn (BoardExpectationType $type): int => abs($type->rating - $score))->first();
                $leagueSize = $competitionId === null ? null : count($peerIds);

                ClubSeasonExpectation::query()->create([
                    'instance_id' => $instance->id, 'season_id' => $season->id, 'club_id' => $club->id,
                    'competition_id' => $competitionId, 'board_expectation_type_id' => $type->id,
                    'description' => $type->description, 'rating' => $type->rating, 'tolerance' => $type->tolerance,
                    'expectation_score' => $score,
                    'target_position' => $leagueSize === null ? null : $this->calculator->targetPosition($type->rating, $leagueSize),
                    'league_size' => $leagueSize, ...$clubInputs,
                    'assigned_on' => CarbonImmutable::parse($instance->instance_date)->toDateString(),
                ]);
            }
        });
    }

    /** @param Collection<int, Player> $players */
    private function teamQuality(Collection $players): float
    {
        $ratings = $players->map(function (Player $player): float {
            $attributes = [
                ...($player->position === 'GK' ? PlayerFields::GOALKEEPING_FIELDS : PlayerFields::TECHNICAL_FIELDS),
                ...PlayerFields::MENTAL_FIELDS, ...PlayerFields::PHYSICAL_FIELDS,
            ];

            return array_sum(array_map(fn (string $attribute): int => (int) $player->{$attribute}, $attributes)) / count($attributes);
        });

        return round((float) ($ratings->sortDesc()->take(11)->average() ?? 0), 2);
    }
}
