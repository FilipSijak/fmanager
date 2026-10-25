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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MatchEngineServiceTest extends TestCase
{
    use RefreshDatabase;

    private const LINEUP_POSITIONS = ['GK', 'CB', 'CB', 'CB', 'CB', 'CM', 'CM', 'CM', 'CM', 'ST', 'ST'];

    #[Test]
    public function clubs_without_a_tactic_or_manager_play_neutral(): void
    {
        $game = $this->game();

        $result = app(MatchEngineService::class)->simulate($game);

        $this->assertSame(['home' => 1.46, 'away' => 1.2], $result->summary['expected_goals']);
    }

    #[Test]
    public function attacking_club_tactic_scores_and_concedes_more(): void
    {
        $game = $this->game();
        $this->clubTactic($game->hometeam_id, FormationTendency::ATTACKING, Mentality::ATTACKING);

        $result = app(MatchEngineService::class)->simulate($game);

        $this->assertSame(['home' => 1.89, 'away' => 1.48], $result->summary['expected_goals']);
    }

    #[Test]
    public function falls_back_to_the_managers_tactic_preference_when_the_club_has_no_tactic(): void
    {
        $game = $this->game();
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
    public function picks_the_best_active_players_when_the_club_has_no_saved_lineup(): void
    {
        $game = $this->game();
        $this->squad($game->hometeam_id, 12);
        $this->squad($game->hometeam_id, 8);
        $this->squad($game->hometeam_id, 20, ['is_retired' => true]);
        $this->squad($game->awayteam_id, 8);

        $result = app(MatchEngineService::class)->simulate($game);

        $this->assertSame(['home' => 2.94, 'away' => 0.59], $result->summary['expected_goals']);
    }

    #[Test]
    public function plays_the_saved_lineup_over_better_players_on_the_bench(): void
    {
        $game = $this->game();
        $tactic = $this->clubTactic($game->hometeam_id, FormationTendency::BALANCED, Mentality::BALANCED);
        $this->squad($game->hometeam_id, 12);
        $this->saveLineup($tactic, $this->squad($game->hometeam_id, 8));
        $this->squad($game->awayteam_id, 8);

        $result = app(MatchEngineService::class)->simulate($game);

        $this->assertSame(['home' => 1.46, 'away' => 1.2], $result->summary['expected_goals']);
    }

    private function game(): Game
    {
        Season::factory()->create(['id' => 1, 'instance_id' => 1]);
        Instance::factory()->create(['id' => 1, 'season_id' => 1]);

        return Game::factory()->make([
            'instance_id' => 1,
            'hometeam_id' => Club::factory()->create(['instance_id' => 1])->id,
            'awayteam_id' => Club::factory()->create(['instance_id' => 1])->id,
        ]);
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
     * An eleven in a 4-4-2 shape with every attribute set to the given rating.
     *
     * @param  array<string, mixed>  $attributes
     * @return Collection<int, Player>
     */
    private function squad(int $clubId, int $rating, array $attributes = []): Collection
    {
        $ratings = array_fill_keys([
            ...PlayerFields::TECHNICAL_FIELDS,
            ...PlayerFields::MENTAL_FIELDS,
            ...PlayerFields::PHYSICAL_FIELDS,
            ...PlayerFields::GOALKEEPING_FIELDS,
        ], $rating);

        return collect(self::LINEUP_POSITIONS)->map(fn (string $position): Player => Player::factory()->create([
            ...$ratings,
            ...$attributes,
            'instance_id' => 1,
            'club_id' => $clubId,
            'position' => $position,
        ]));
    }

    /** @param Collection<int, Player> $players */
    private function saveLineup(ClubTactic $tactic, Collection $players): void
    {
        foreach ($players->values() as $index => $player) {
            ClubTacticPlayerSlot::query()->create([
                'instance_id' => 1,
                'club_id' => $tactic->club_id,
                'club_tactic_id' => $tactic->id,
                'player_id' => $player->id,
                'slot' => (string) $index,
                'position' => $player->position,
            ]);
        }
    }
}
