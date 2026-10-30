<?php

namespace Tests\Feature;

use App\Domain\PlayerDevelopment\TrainingCategory;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Player;
use App\Models\Season;
use App\Services\TrainingService\TrainingService;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TrainingManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_cannot_view_or_change_training(): void
    {
        $this->get('/training')->assertRedirect('/login');
        $this->getJson('/api/training')->assertUnauthorized();
        $this->putJson('/api/training', [])->assertUnauthorized();
    }

    #[Test]
    public function the_training_page_shows_the_managed_squad_and_saved_settings(): void
    {
        [$instance, $club, $player] = $this->world();
        DB::table('players_progress')->where('player_id', $player->id)->update(['condition' => 82, 'strength' => 75]);
        Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id, 'is_retired' => true]);
        $otherClub = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => 200]);
        Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $otherClub->id]);

        $this->withoutVite()->get('/training')->assertInertia(fn (Assert $page) => $page
            ->component('training/Training')->where('club.id', $club->id)->where('date', '2026-09-01')
            ->has('players', 1)->where('players.0.id', $player->id)->where('players.0.condition', 82)
            ->where('players.0.schedule.physical', 2)->where('players.0.resting', false)
            ->has('categories', 4)->where('categories.1.threshold', 200)->has('presets', 8));
    }

    #[Test]
    public function the_api_exposes_attributes_and_progress_without_hidden_ability_values(): void
    {
        [, , $player] = $this->world();
        $player->forceFill(['pace' => 12])->save();
        DB::table('players_progress')->where('player_id', $player->id)->update(['pace' => 75]);

        $response = $this->getJson('/api/training');

        $response->assertOk()->assertJsonPath('data.players.0.id', $player->id)
            ->assertJsonPath('data.players.0.attributes.physical.5', ['key' => 'pace', 'name' => 'Pace', 'value' => 12, 'progress' => 75])
            ->assertJsonMissingPath('data.players.0.potential')->assertJsonMissingPath('data.players.0.max_potential');
    }

    #[Test]
    public function reading_training_does_not_create_or_change_schedules(): void
    {
        [, , $player] = $this->world();
        DB::table('training_player_schedule')->where('player_id', $player->id)->delete();

        $this->getJson('/api/training')->assertOk();
        $this->assertDatabaseCount('training_player_schedule', 0);
    }

    #[Test]
    public function individual_and_shared_schedules_save_to_existing_rows(): void
    {
        [$instance, $club, $player] = $this->world();
        $teammate = Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id]);
        $untouched = Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id]);
        $schedule = ['physical' => 3, 'tactical' => 1, 'technical' => 2, 'goalkeeping' => 4];

        $this->putJson('/api/training', ['player_ids' => [$player->id, $teammate->id], 'schedule' => $schedule, 'condition' => 0])->assertOk();

        $this->assertDatabaseCount('training_player_schedule', 12);
        foreach ([$player, $teammate] as $changed) {
            $this->assertDatabaseHas('training_player_schedule', ['player_id' => $changed->id, 'training_category_id' => 1, 'training_intensity_id' => 3]);
            $this->assertDatabaseHas('training_player_schedule', ['player_id' => $changed->id, 'training_category_id' => 2, 'training_intensity_id' => 1]);
            $this->assertDatabaseHas('training_player_schedule', ['player_id' => $changed->id, 'training_category_id' => 4, 'training_intensity_id' => 4]);
        }
        $this->assertDatabaseHas('training_player_schedule', ['player_id' => $untouched->id, 'training_category_id' => 1, 'training_intensity_id' => 2]);
        $this->assertDatabaseHas('players_progress', ['player_id' => $player->id, 'condition' => 100]);
        $this->getJson('/api/training')->assertOk()->assertJsonPath('data.players.0.schedule', $schedule);
    }

    #[Test]
    public function saving_a_plan_repairs_a_missing_category_without_creating_duplicates(): void
    {
        [, , $player] = $this->world();
        DB::table('training_player_schedule')->where('player_id', $player->id)->where('training_category_id', 1)->delete();

        $this->putJson('/api/training', $this->payload($player))->assertOk();

        $this->assertDatabaseCount('training_player_schedule', 4);
        $this->assertDatabaseHas('training_player_schedule', ['player_id' => $player->id, 'training_category_id' => 1, 'training_intensity_id' => 2]);
    }

    #[Test]
    public function a_focus_plan_changes_actual_progress_on_the_next_training_day(): void
    {
        [$instance, , $player] = $this->world();
        $player->forceFill(['marking' => 10, 'strength' => 10])->save();
        DB::table('players_progress')->where('player_id', $player->id)->update(['marking' => 50, 'strength' => 50]);
        $payload = $this->payload($player);
        $payload['schedule'] = ['physical' => 1, 'tactical' => 1, 'technical' => 3, 'goalkeeping' => 1];
        $this->putJson('/api/training', $payload)->assertOk();

        app(TrainingService::class)->processDay($instance);

        $this->assertDatabaseHas('players_progress', ['player_id' => $player->id, 'marking' => 55, 'strength' => 50]);
    }

    #[Test]
    public function full_rest_recovers_a_tired_player_and_preserves_accumulated_progress(): void
    {
        [$instance, , $player] = $this->world();
        DB::table('players_progress')->where('player_id', $player->id)->update(['condition' => 65, 'pace' => 75]);
        $payload = $this->payload($player, 4);
        $this->putJson('/api/training', $payload)->assertOk();
        $this->getJson('/api/training')->assertJsonPath('data.players.0.resting', true)->assertJsonPath('data.players.0.condition_change', 10);

        app(TrainingService::class)->processDay($instance);

        $this->assertDatabaseHas('players_progress', ['player_id' => $player->id, 'condition' => 75, 'pace' => 75]);
    }

    #[Test]
    public function resuming_training_after_rest_uses_the_new_plan(): void
    {
        [$instance, , $player] = $this->world();
        DB::table('players_progress')->where('player_id', $player->id)->update(['condition' => 80]);
        $this->putJson('/api/training', $this->payload($player, 4))->assertOk();
        $this->putJson('/api/training', $this->payload($player, 3))->assertOk();

        app(TrainingService::class)->processDay($instance);

        $this->assertDatabaseHas('players_progress', ['player_id' => $player->id, 'condition' => 75]);
        $this->getJson('/api/training')->assertJsonPath('data.players.0.resting', false)->assertJsonPath('data.players.0.workload', 'Heavy');
    }

    #[Test]
    public function injured_players_are_identified_using_the_game_date(): void
    {
        [$instance, , $player] = $this->world();
        DB::table('injuries')->insert(['id' => 1, 'type' => 'Knee injury', 'severity' => 4, 'duration_from' => 1, 'duration_to' => 20]);
        DB::table('player_injuries')->insert(['instance_id' => $instance->id, 'season_id' => $instance->season_id, 'player_id' => $player->id, 'injury_id' => 1, 'injury_start_date' => '2026-08-31', 'injury_end_date' => '2026-09-05']);

        $this->getJson('/api/training')->assertOk()->assertJsonPath('data.players.0.is_injured', true)
            ->assertJsonPath('data.players.0.workload', 'Unavailable')->assertJsonPath('data.players.0.condition_change', 0);
    }

    #[Test]
    public function goalkeepers_receive_goalkeeping_progress_and_the_correct_workload(): void
    {
        [, , $player] = $this->world();
        $player->forceFill(['position' => 'GK', 'handling' => 12])->save();
        DB::table('players_progress')->where('player_id', $player->id)->update(['condition' => 90, 'handling' => 99]);

        $this->getJson('/api/training')->assertOk()->assertJsonPath('data.players.0.attributes.goalkeeping.0', ['key' => 'handling', 'name' => 'Handling', 'value' => 12, 'progress' => 99])
            ->assertJsonPath('data.players.0.workload', 'Heavy')->assertJsonPath('data.players.0.condition_change', -5);
    }

    #[Test]
    public function matchdays_still_skip_training_and_the_day_after_a_match_recovers_condition(): void
    {
        [$instance, $club, $player] = $this->world();
        $opponent = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => 200]);
        $competition = Competition::factory()->create(['instance_id' => $instance->id]);
        Game::factory()->create(['instance_id' => $instance->id, 'season_id' => $instance->season_id, 'competition_id' => $competition->id, 'stadium_id' => $club->stadium_id, 'hometeam_id' => $club->id, 'awayteam_id' => $opponent->id, 'match_start' => '2026-09-01 15:00:00', 'status' => Game::STATUS_SCHEDULED]);
        DB::table('players_progress')->where('player_id', $player->id)->update(['condition' => 80, 'marking' => 50]);
        $this->putJson('/api/training', $this->payload($player, 3))->assertOk();

        app(TrainingService::class)->processDay($instance);
        $this->assertDatabaseHas('players_progress', ['player_id' => $player->id, 'condition' => 80, 'marking' => 50]);
        $instance->forceFill(['instance_date' => '2026-09-02'])->save();
        app(TrainingService::class)->processDay($instance);
        $this->assertDatabaseHas('players_progress', ['player_id' => $player->id, 'condition' => 90, 'marking' => 50]);
    }

    #[Test]
    public function a_bulk_update_with_another_clubs_player_returns_404_and_changes_nothing(): void
    {
        [$instance, , $player] = $this->world();
        $other = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => 200]);
        $outsider = Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $other->id]);

        $this->putJson('/api/training', ['player_ids' => [$player->id, $outsider->id], 'schedule' => $this->payload($player, 4)['schedule']])->assertNotFound();

        $this->assertDatabaseMissing('training_player_schedule', ['player_id' => $player->id, 'training_intensity_id' => 4]);
        $this->assertDatabaseMissing('training_player_schedule', ['player_id' => $outsider->id, 'training_intensity_id' => 4]);
    }

    #[Test]
    public function retired_players_cannot_have_their_schedules_changed(): void
    {
        [, , $player] = $this->world();
        $player->forceFill(['is_retired' => true])->save();

        $this->putJson('/api/training', $this->payload($player))->assertNotFound();
    }

    #[Test]
    public function another_saves_player_cannot_be_read_or_updated(): void
    {
        [$instance, $club, $player] = $this->world();
        $foreignInstance = Instance::factory()->create(['club_id' => $club->id, 'season_id' => $instance->season_id]);
        $player->forceFill(['instance_id' => $foreignInstance->id])->save();

        $this->getJson('/api/training')->assertOk()->assertJsonCount(0, 'data.players');
        $this->putJson('/api/training', $this->payload($player))->assertNotFound();
    }

    #[Test]
    public function unemployed_managers_cannot_access_training(): void
    {
        [$instance] = $this->world();
        $instance->forceFill(['club_id' => null])->save();

        $this->get('/training')->assertRedirect('/');
        $this->getJson('/api/training')->assertForbidden();
        $this->putJson('/api/training', [])->assertForbidden();
    }

    #[Test]
    public function missing_player_selection_and_schedule_return_422(): void
    {
        $this->world();

        $this->putJson('/api/training', [])->assertUnprocessable()->assertJsonValidationErrors(['player_ids', 'schedule']);
    }

    #[Test]
    #[TestWith(['schedule.technical', 0])]
    #[TestWith(['schedule.physical', 5])]
    #[TestWith(['schedule.tactical', 1.5])]
    #[TestWith(['schedule.goalkeeping', 'hard'])]
    #[TestWith(['schedule.invalid_category', 2])]
    #[TestWith(['player_ids', []])]
    public function invalid_training_settings_return_422(string $field, mixed $value): void
    {
        [, , $player] = $this->world();
        $payload = $this->payload($player);
        data_set($payload, $field, $value);

        $this->putJson('/api/training', $payload)->assertUnprocessable()->assertJsonValidationErrors(str_starts_with($field, 'schedule.invalid') ? 'schedule' : $field);
    }

    #[Test]
    public function repeated_player_ids_are_rejected_instead_of_applying_a_partial_update(): void
    {
        [, , $player] = $this->world();
        $payload = $this->payload($player);
        $payload['player_ids'][] = $player->id;

        $this->putJson('/api/training', $payload)->assertUnprocessable()->assertJsonValidationErrors('player_ids.0');
    }

    #[Test]
    public function condition_previews_respect_full_fitness_and_the_heavy_training_floor(): void
    {
        [, , $player] = $this->world();
        $this->getJson('/api/training')->assertOk()->assertJsonPath('data.players.0.condition_change', 0);
        DB::table('players_progress')->where('player_id', $player->id)->update(['condition' => 70]);
        $this->putJson('/api/training', $this->payload($player, 3))->assertOk();

        $this->getJson('/api/training')->assertOk()->assertJsonPath('data.players.0.workload', 'Heavy')->assertJsonPath('data.players.0.condition_change', 0);
    }

    /** @return array{Instance, Club, Player} */
    private function world(): array
    {
        $instance = Instance::factory()->create(['instance_date' => '2026-09-01']);
        $season = Season::factory()->create(['instance_id' => $instance->id]);
        $club = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => 100]);
        $instance->forceFill(['club_id' => $club->id, 'season_id' => $season->id])->save();
        $player = Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id, 'position' => 'CB', 'technical' => 180, 'mental' => 180, 'physical' => 180, 'current_technical_potential' => 150, 'current_mental_potential' => 150, 'current_physical_potential' => 150, 'potential' => 100, 'max_potential' => 180]);
        $this->actingAs($instance->user)->withHeaders(['instanceHash' => $instance->instance_hash])->withSession(['active_instance_hash' => $instance->instance_hash]);
        app(GameContext::class)->set($instance->id, $season->id, '2026-09-01');

        return [$instance, $club, $player];
    }

    /** @return array{player_ids: list<int>, schedule: array<string, int>} */
    private function payload(Player $player, int $intensity = 2): array
    {
        return ['player_ids' => [$player->id], 'schedule' => collect(TrainingCategory::cases())->mapWithKeys(fn (TrainingCategory $category): array => [strtolower($category->name) => $intensity])->all()];
    }
}
