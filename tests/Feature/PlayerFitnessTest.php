<?php

namespace Tests\Feature;

use App\Domain\PlayerDevelopment\TrainingCategory;
use App\Events\PostMatch;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Player;
use App\Models\Season;
use App\Models\Stadium;
use App\Services\GameService\CompleteGameService;
use App\Services\MatchEngineService\MatchTeamBuilder;
use App\Services\TrainingService\PlayerFitnessService;
use App\Services\TrainingService\TrainingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Random\Engine;
use Random\Randomizer;
use RuntimeException;
use Tests\TestCase;

class PlayerFitnessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function completing_a_match_applies_minutes_based_fatigue_and_injuries_once(): void
    {
        [$instance, $club, $player, $game] = $this->world();
        $player->forceFill(['workrate' => 20, 'teamwork' => 20])->save();
        $substitute = Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id, 'stamina' => 10, 'workrate' => 10, 'teamwork' => 10]);
        $unused = Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id]);
        Event::fake([PostMatch::class]);
        $stats = [
            ['player_id' => $player->id, 'club_id' => $club->id, 'position' => 'CM', 'started' => true, 'minutes_played' => 60, 'rating' => 6.5],
            ['player_id' => $substitute->id, 'club_id' => $club->id, 'position' => 'CM', 'started' => false, 'minutes_played' => 30, 'rating' => 6.5],
        ];
        $summary = ['events' => [['type' => 'injury', 'player_id' => $player->id, 'minute' => 60, 'injury_days' => 7]]];
        $service = app(CompleteGameService::class);

        $service->complete($game->id, 0, 0, $summary, $stats);
        $service->complete($game->id, 0, 0, $summary, $stats);

        $this->assertDatabaseHas('players_progress', ['player_id' => $player->id, 'condition' => 80]);
        $this->assertDatabaseHas('players_progress', ['player_id' => $substitute->id, 'condition' => 92]);
        $this->assertDatabaseHas('players_progress', ['player_id' => $unused->id, 'condition' => 100]);
        $this->assertDatabaseCount('player_injuries', 1);
        $this->assertDatabaseHas('player_injuries', ['player_id' => $player->id, 'injury_start_date' => '2030-08-15', 'injury_end_date' => '2030-08-21']);
        $this->assertDatabaseHas('news', ['instance_id' => $instance->id, 'club_id' => $club->id, 'type' => 'injury']);
        $this->assertDatabaseCount('news', 1);
    }

    #[Test]
    public function failed_match_completion_rolls_back_condition_injuries_and_news(): void
    {
        [$instance, $club, $player, $game] = $this->world();
        Event::forget(PostMatch::class);
        Event::listen(PostMatch::class, function (): void {
            throw new RuntimeException('Match completion failed.');
        });
        try {
            app(CompleteGameService::class)->complete($game->id, 0, 0, ['events' => [
                ['type' => 'injury', 'player_id' => $player->id, 'injury_days' => 7],
            ]], [['player_id' => $player->id, 'club_id' => $club->id, 'position' => 'CM', 'started' => true, 'minutes_played' => 60, 'rating' => 6.5]]);
            $this->fail('Expected completion to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Match completion failed.', $exception->getMessage());
        }

        $this->assertDatabaseHas('players_progress', ['player_id' => $player->id, 'condition' => 100]);
        $this->assertDatabaseCount('player_injuries', 0);
        $this->assertDatabaseCount('news', 0);
        $this->assertNull($game->fresh()->processed_at);
    }

    #[Test]
    public function daily_training_generates_injuries_and_recovery_is_reported_once_after_the_end_date(): void
    {
        [$instance, $club, $player] = $this->world();
        $instance->forceFill(['instance_date' => '2030-08-10'])->save();
        DB::table('training_player_schedule')->where('player_id', $player->id)
            ->where('training_category_id', TrainingCategory::Physical->value)->update(['training_intensity_id' => 3]);
        $this->app->instance(Randomizer::class, new Randomizer(new class implements Engine
        {
            public function generate(): string
            {
                return str_repeat("\0", 8);
            }
        }));
        $service = app(TrainingService::class);

        $service->processDay($instance);
        $service->processDay($instance);

        $this->assertDatabaseCount('player_injuries', 1);
        $this->assertDatabaseCount('news', 1);
        $this->assertDatabaseHas('player_injuries', ['player_id' => $player->id, 'injury_end_date' => '2030-08-12']);
        $this->assertFalse(Player::query()->whereKey($player->id)->notInjuredOn('2030-08-12')->exists());
        DB::table('players_progress')->where('player_id', $player->id)->update(['condition' => 60]);
        $instance->forceFill(['instance_date' => '2030-08-11'])->save();
        $service->processDay($instance);
        $service->processDay($instance);
        $this->assertDatabaseHas('players_progress', ['player_id' => $player->id, 'condition' => 70]);
        $this->assertDatabaseCount('player_injuries', 1);
        $this->assertDatabaseCount('news', 1);
        $instance->forceFill(['instance_date' => '2030-08-13'])->save();

        app(PlayerFitnessService::class)->reportRecoveries($instance);
        app(PlayerFitnessService::class)->reportRecoveries($instance);

        $this->assertTrue(Player::query()->whereKey($player->id)->notInjuredOn('2030-08-13')->exists());
        $this->assertDatabaseCount('news', 2);
        $this->assertDatabaseHas('player_injuries', ['player_id' => $player->id, 'recovery_reported_on' => '2030-08-13']);
    }

    #[Test]
    public function rest_recovers_condition_once_per_day_and_does_not_cause_injuries(): void
    {
        [$instance, , $player] = $this->world();
        $instance->forceFill(['instance_date' => '2030-08-10'])->save();
        DB::table('players_progress')->where('player_id', $player->id)->update(['condition' => 50]);
        foreach (TrainingCategory::cases() as $category) {
            DB::table('training_player_schedule')->where('player_id', $player->id)
                ->where('training_category_id', $category->value)->update(['training_intensity_id' => 4]);
        }

        app(TrainingService::class)->processDay($instance);
        app(TrainingService::class)->processDay($instance);

        $this->assertDatabaseHas('players_progress', ['player_id' => $player->id, 'condition' => 60]);
        $this->assertDatabaseCount('player_injuries', 0);
    }

    #[Test]
    public function low_condition_reduces_match_ratings_without_changing_player_attributes(): void
    {
        [, $club, $player] = $this->world();
        $builder = app(MatchTeamBuilder::class);
        $fresh = collect($builder->forClub($club->id, '2030-08-15')->players)->firstWhere('playerId', $player->id);
        DB::table('players_progress')->where('player_id', $player->id)->update(['condition' => 50]);

        $tired = collect($builder->forClub($club->id, '2030-08-15')->players)->firstWhere('playerId', $player->id);

        $this->assertEqualsWithDelta($fresh->passing * 0.75, $tired->passing, 0.00001);
        $this->assertSame(50, $tired->condition);
        $this->assertSame($player->workrate, $tired->workrate);
        $this->assertSame($player->teamwork, $tired->teamwork);
        $this->assertSame($player->passing, $player->fresh()->passing);
    }

    /** @return array{Instance, Club, Player, Game} */
    private function world(): array
    {
        $instance = Instance::factory()->create(['instance_date' => '2030-08-15']);
        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2030-08-01', 'end_date' => '2031-06-15']);
        $stadium = Stadium::factory()->create(['instance_id' => $instance->id]);
        $club = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => $stadium->id]);
        $away = Club::factory()->create(['instance_id' => $instance->id]);
        $instance->forceFill(['season_id' => $season->id, 'club_id' => $club->id])->save();
        $player = Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id, 'position' => 'CM', 'stamina' => 10, 'workrate' => 10, 'teamwork' => 10]);
        $competition = Competition::factory()->create(['instance_id' => $instance->id, 'type' => 'league', 'clubs_number' => 2]);
        foreach ([$club, $away] as $participant) {
            DB::table('competition_season')->insert(['instance_id' => $instance->id, 'season_id' => $season->id, 'competition_id' => $competition->id, 'club_id' => $participant->id]);
        }
        $game = Game::factory()->create(['instance_id' => $instance->id, 'season_id' => $season->id, 'competition_id' => $competition->id,
            'hometeam_id' => $club->id, 'awayteam_id' => $away->id, 'stadium_id' => $club->stadium_id, 'match_start' => '2030-08-15 15:00:00']);

        return [$instance, $club, $player, $game];
    }
}
