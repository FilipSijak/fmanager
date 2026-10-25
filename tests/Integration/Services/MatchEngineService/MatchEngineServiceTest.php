<?php

namespace Tests\Integration\Services\MatchEngineService;

use App\Models\BaseData\BaseFormation;
use App\Models\Club;
use App\Models\ClubTactic;
use App\Models\ClubTacticPlayerSlot;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Player;
use App\Models\Season;
use App\Models\StaffCoaching;
use App\Models\StaffTacticPreference;
use App\Services\MatchEngineService\MatchEngineService;
use App\Services\PersonService\PersonConfig\Player\PlayerFields;
use App\Services\TacticsService\FormationTendency;
use App\Services\TacticsService\Mentality;
use App\Services\TacticsService\PassingStyle;
use App\Services\TacticsService\PressingIntensity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MatchEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    private const LINEUP_POSITIONS = ['GK', 'CB', 'CB', 'CB', 'CB', 'CM', 'CM', 'CM', 'CM', 'ST', 'ST'];

    private const BENCH_POSITIONS = ['GK', 'CB', 'CB', 'CM', 'CM', 'ST', 'ST'];

    #[Test]
    public function clubs_without_a_tactic_or_manager_play_neutral(): void
    {
        $game = $this->game();
        $this->averageSquads($game);

        $result = app(MatchEngineService::class)->simulate($game);

        $this->assertSame(['home' => 1.46, 'away' => 1.2], $result->summary['expected_goals']);
    }

    #[Test]
    public function attacking_club_tactic_scores_and_concedes_more(): void
    {
        $game = $this->game();
        $this->averageSquads($game);
        $this->clubTactic($game->hometeam_id, FormationTendency::ATTACKING, Mentality::ATTACKING);

        $result = app(MatchEngineService::class)->simulate($game);

        $this->assertSame(['home' => 1.89, 'away' => 1.48], $result->summary['expected_goals']);
    }

    #[Test]
    public function falls_back_to_the_managers_tactic_preference_when_the_club_has_no_tactic(): void
    {
        $game = $this->game();
        $this->averageSquads($game);
        StaffTacticPreference::query()->create([
            'staff_coaching_id' => StaffCoaching::factory()->create(['club_id' => $game->hometeam_id])->id,
            'base_formation_id' => $this->formation(FormationTendency::DEFENSIVE)->id,
            'mentality' => Mentality::DEFENSIVE,
            'pressing' => PressingIntensity::LOW,
            'passing' => PassingStyle::DIRECT,
        ]);

        $result = app(MatchEngineService::class)->simulate($game);

        $this->assertSame(['home' => 1.02, 'away' => 0.91], $result->summary['expected_goals']);
    }

    #[Test]
    public function picks_the_best_active_eleven_and_bench_when_the_club_has_no_saved_lineup(): void
    {
        $game = $this->game();
        $starters = $this->squad($game->hometeam_id, 12);
        $substitutes = $this->squad($game->hometeam_id, 8, positions: self::BENCH_POSITIONS);
        $this->squad($game->hometeam_id, 5, positions: ['CB']);
        $this->squad($game->hometeam_id, 20, ['is_retired' => true]);
        $this->squad($game->awayteam_id, 8);

        $result = app(MatchEngineService::class)->simulate($game);

        $this->assertSame(['home' => 2.12, 'away' => 0.78], $result->summary['expected_goals']);
        $this->assertSame([
            'starting' => $starters->pluck('id')->all(),
            'substitutes' => $substitutes->pluck('id')->all(),
        ], $result->summary['lineups']['home']);
    }

    #[Test]
    public function plays_the_saved_starters_and_keeps_the_saved_bench_out_of_the_ratings(): void
    {
        $game = $this->game();
        $tactic = $this->clubTactic($game->hometeam_id, FormationTendency::BALANCED, Mentality::BALANCED);
        $starters = $this->squad($game->hometeam_id, 8);
        $substitutes = $this->squad($game->hometeam_id, 12, positions: self::BENCH_POSITIONS);
        $this->saveLineup($tactic, $starters, $substitutes);
        $this->squad($game->awayteam_id, 8);

        $result = app(MatchEngineService::class)->simulate($game);

        $this->assertSame(['home' => 1.39, 'away' => 1.15], $result->summary['expected_goals']);
        $this->assertSame([
            'starting' => $starters->pluck('id')->all(),
            'substitutes' => $substitutes->pluck('id')->all(),
        ], $result->summary['lineups']['home']);
    }

    #[Test]
    public function replaces_a_saved_starter_who_left_the_club_from_the_saved_bench_without_refilling_the_bench(): void
    {
        $game = $this->game();
        $tactic = $this->clubTactic($game->hometeam_id, FormationTendency::BALANCED, Mentality::BALANCED);
        $starters = $this->squad($game->hometeam_id, 10);
        $substitutes = $this->squad($game->hometeam_id, 8, positions: self::BENCH_POSITIONS);
        $this->squad($game->hometeam_id, 6, positions: ['CM']);
        $this->saveLineup($tactic, $starters, $substitutes);
        $soldDefender = $starters[1];
        $soldDefender->forceFill(['club_id' => null])->save();
        $replacementDefender = $substitutes[1];

        $result = app(MatchEngineService::class)->simulate($game);

        $this->assertSame([
            'starting' => [...$starters->except(1)->pluck('id')->all(), $replacementDefender->id],
            'substitutes' => $substitutes->except(1)->pluck('id')->all(),
        ], $result->summary['lineups']['home']);
    }

    #[Test]
    public function replaces_a_saved_starter_injured_on_match_day_from_the_saved_bench(): void
    {
        $game = $this->game();
        $tactic = $this->clubTactic($game->hometeam_id, FormationTendency::BALANCED, Mentality::BALANCED);
        $starters = $this->squad($game->hometeam_id, 10);
        $substitutes = $this->squad($game->hometeam_id, 8, positions: self::BENCH_POSITIONS);
        $this->saveLineup($tactic, $starters, $substitutes);
        DB::table('injuries')->insert(['id' => 1, 'type' => 'Knee injury', 'severity' => 4, 'duration_from' => 10, 'duration_to' => 20]);
        DB::table('player_injuries')->insert([
            'instance_id' => 1, 'season_id' => 1, 'player_id' => $starters[1]->id, 'injury_id' => 1,
            'injury_start_date' => '2026-08-10', 'injury_end_date' => '2026-08-15',
        ]);

        $result = app(MatchEngineService::class)->simulate($game);

        $this->assertSame([
            'starting' => [...$starters->except(1)->pluck('id')->all(), $substitutes[1]->id],
            'substitutes' => $substitutes->except(1)->pluck('id')->all(),
        ], $result->summary['lineups']['home']);
    }

    #[Test]
    public function saved_lineup_without_substitutes_plays_with_an_empty_bench(): void
    {
        $game = $this->game();
        $tactic = $this->clubTactic($game->hometeam_id, FormationTendency::BALANCED, Mentality::BALANCED);
        $starters = $this->squad($game->hometeam_id, 8);
        $this->squad($game->hometeam_id, 12, positions: self::BENCH_POSITIONS);
        $this->saveLineup($tactic, $starters, collect());

        $result = app(MatchEngineService::class)->simulate($game);

        $this->assertSame([
            'starting' => $starters->pluck('id')->all(),
            'substitutes' => [],
        ], $result->summary['lineups']['home']);
    }

    private function game(): Game
    {
        Season::factory()->create(['id' => 1, 'instance_id' => 1]);
        Instance::factory()->create(['id' => 1, 'season_id' => 1]);

        return Game::factory()->make([
            'instance_id' => 1,
            'hometeam_id' => Club::factory()->create(['instance_id' => 1])->id,
            'awayteam_id' => Club::factory()->create(['instance_id' => 1])->id,
            'match_start' => '2026-08-15 15:00:00',
        ]);
    }

    private function averageSquads(Game $game): void
    {
        $this->squad($game->hometeam_id, 10);
        $this->squad($game->awayteam_id, 10);
    }

    private function clubTactic(int $clubId, FormationTendency $formationTendency, Mentality $mentality): ClubTactic
    {
        return ClubTactic::query()->create([
            'club_id' => $clubId,
            'base_formation_id' => $this->formation($formationTendency)->id,
            'name' => 'Manager tactic',
            'mentality' => $mentality,
            'pressing' => PressingIntensity::MEDIUM,
            'passing' => PassingStyle::MIXED,
        ]);
    }

    private function formation(FormationTendency $tendency): BaseFormation
    {
        return BaseFormation::query()->create(['code' => $tendency->value, 'name' => $tendency->value, 'is_active' => true, 'tactical_tendency' => $tendency]);
    }

    /**
     * Players in the given positions (an eleven in a 4-4-2 shape by default) with every attribute set to the given rating.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $positions
     * @return Collection<int, Player>
     */
    private function squad(int $clubId, int $rating, array $attributes = [], array $positions = self::LINEUP_POSITIONS): Collection
    {
        $ratings = array_fill_keys([
            ...PlayerFields::TECHNICAL_FIELDS,
            ...PlayerFields::MENTAL_FIELDS,
            ...PlayerFields::PHYSICAL_FIELDS,
            ...PlayerFields::GOALKEEPING_FIELDS,
        ], $rating);

        return collect($positions)->map(fn (string $position): Player => Player::factory()->create([
            ...$ratings,
            ...$attributes,
            'instance_id' => 1,
            'club_id' => $clubId,
            'position' => $position,
        ]));
    }

    /**
     * Saves the selection the way the lineup screen does: formation slots for starters, SUB slots for the bench.
     *
     * @param  Collection<int, Player>  $starters
     * @param  Collection<int, Player>  $substitutes
     */
    private function saveLineup(ClubTactic $tactic, Collection $starters, Collection $substitutes): void
    {
        $slots = [
            ...$starters->values()->map(fn (Player $player, int $index): array => ['slot' => 'S'.($index + 1), 'player' => $player, 'position' => $player->position]),
            ...$substitutes->values()->map(fn (Player $player, int $index): array => ['slot' => 'SUB'.($index + 1), 'player' => $player, 'position' => 'SUB']),
        ];

        foreach ($slots as $slot) {
            ClubTacticPlayerSlot::query()->create([
                'instance_id' => 1,
                'club_id' => $tactic->club_id,
                'club_tactic_id' => $tactic->id,
                'player_id' => $slot['player']->id,
                'slot' => $slot['slot'],
                'position' => $slot['position'],
            ]);
        }
    }
}
