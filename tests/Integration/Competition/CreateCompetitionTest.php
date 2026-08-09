<?php

namespace Tests\Integration\Competition;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Instance;
use App\Models\Season;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateCompetitionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_a_competition_for_the_current_instance(): void
    {
        $instance = Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
        ]);
        $competition = Competition::factory()->create([
            'id' => 10,
            'instance_id' => $instance->id,
            'name' => 'Premier League',
            'country_code' => 'GB',
            'type' => 'league',
            'groups' => 0,
        ]);

        $response = $this
            ->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson("/api/competition/{$competition->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $competition->id)
            ->assertJsonPath('data.name', 'Premier League')
            ->assertJsonPath('data.country_code', 'GB')
            ->assertJsonPath('data.type', 'league')
            ->assertJsonPath('data.groups', 0);
    }

    #[Test]
    public function it_does_not_return_a_competition_from_another_instance(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
        ]);
        $otherInstance = Instance::factory()->create([
            'id' => 2,
            'instance_hash' => 'other-instance',
        ]);
        $otherCompetition = Competition::factory()->create([
            'id' => 10,
            'instance_id' => $otherInstance->id,
        ]);

        $response = $this
            ->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson("/api/competition/{$otherCompetition->id}");

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_the_competition_table_with_positions_and_stats(): void
    {
        $instance = Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
            'season_id' => 1,
        ]);
        Season::factory()->create([
            'id' => 1,
            'instance_id' => $instance->id,
        ]);
        $competition = Competition::factory()->create([
            'id' => 10,
            'instance_id' => $instance->id,
        ]);
        Competition::factory()->create([
            'id' => 11,
            'instance_id' => $instance->id,
        ]);

        $delta = Club::factory()->create([
            'id' => 1,
            'instance_id' => $instance->id,
            'name' => 'Delta FC',
        ]);
        $beta = Club::factory()->create([
            'id' => 2,
            'instance_id' => $instance->id,
            'name' => 'Beta FC',
        ]);
        $charlie = Club::factory()->create([
            'id' => 3,
            'instance_id' => $instance->id,
            'name' => 'Charlie FC',
        ]);
        $alpha = Club::factory()->create([
            'id' => 4,
            'instance_id' => $instance->id,
            'name' => 'Alpha FC',
        ]);
        $otherCompetitionClub = Club::factory()->create([
            'id' => 5,
            'instance_id' => $instance->id,
            'name' => 'Other Competition FC',
        ]);

        DB::table('competition_season')->insert([
            [
                'instance_id' => $instance->id,
                'competition_id' => $competition->id,
                'season_id' => 1,
                'club_id' => $alpha->id,
                'points' => 10,
                'goals_for' => 9,
                'goals_against' => 4,
                'played' => 5,
                'wins' => 3,
                'draws' => 1,
                'losses' => 1,
            ],
            [
                'instance_id' => $instance->id,
                'competition_id' => $competition->id,
                'season_id' => 1,
                'club_id' => $charlie->id,
                'points' => 10,
                'goals_for' => 10,
                'goals_against' => 5,
                'played' => 5,
                'wins' => 3,
                'draws' => 1,
                'losses' => 1,
            ],
            [
                'instance_id' => $instance->id,
                'competition_id' => $competition->id,
                'season_id' => 1,
                'club_id' => $beta->id,
                'points' => 10,
                'goals_for' => 10,
                'goals_against' => 5,
                'played' => 5,
                'wins' => 3,
                'draws' => 1,
                'losses' => 1,
            ],
            [
                'instance_id' => $instance->id,
                'competition_id' => $competition->id,
                'season_id' => 1,
                'club_id' => $delta->id,
                'points' => 12,
                'goals_for' => 8,
                'goals_against' => 6,
                'played' => 5,
                'wins' => 4,
                'draws' => 0,
                'losses' => 1,
            ],
            [
                'instance_id' => $instance->id,
                'competition_id' => 11,
                'season_id' => 1,
                'club_id' => $otherCompetitionClub->id,
                'points' => 30,
                'goals_for' => 20,
                'goals_against' => 1,
                'played' => 5,
                'wins' => 5,
                'draws' => 0,
                'losses' => 0,
            ],
        ]);

        $response = $this
            ->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson("/api/competition/{$competition->id}/table");

        $response
            ->assertOk()
            ->assertJsonPath('data.0.position', 1)
            ->assertJsonPath('data.0.club_id', $delta->id)
            ->assertJsonPath('data.0.club_name', 'Delta FC')
            ->assertJsonPath('data.0.played', 5)
            ->assertJsonPath('data.0.wins', 4)
            ->assertJsonPath('data.0.draws', 0)
            ->assertJsonPath('data.0.losses', 1)
            ->assertJsonPath('data.0.goals_for', 8)
            ->assertJsonPath('data.0.goals_against', 6)
            ->assertJsonPath('data.0.goal_difference', 2)
            ->assertJsonPath('data.0.points', 12)
            ->assertJsonPath('data.1.position', 2)
            ->assertJsonPath('data.1.club_id', $beta->id)
            ->assertJsonPath('data.2.position', 3)
            ->assertJsonPath('data.2.club_id', $charlie->id)
            ->assertJsonPath('data.3.position', 4)
            ->assertJsonPath('data.3.club_id', $alpha->id)
            ->assertJsonCount(4, 'data');
    }

    #[Test]
    public function it_returns_tournament_group_tables_mapped_by_group_with_positions(): void
    {
        $instance = Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
            'season_id' => 1,
        ]);
        Season::factory()->create([
            'id' => 1,
            'instance_id' => $instance->id,
        ]);
        $competition = Competition::factory()->create([
            'id' => 10,
            'instance_id' => $instance->id,
            'type' => 'tournament',
            'groups' => 1,
        ]);

        $alpha = Club::factory()->create([
            'id' => 1,
            'instance_id' => $instance->id,
            'name' => 'Alpha FC',
        ]);
        $beta = Club::factory()->create([
            'id' => 2,
            'instance_id' => $instance->id,
            'name' => 'Beta FC',
        ]);
        $charlie = Club::factory()->create([
            'id' => 3,
            'instance_id' => $instance->id,
            'name' => 'Charlie FC',
        ]);
        $delta = Club::factory()->create([
            'id' => 4,
            'instance_id' => $instance->id,
            'name' => 'Delta FC',
        ]);

        DB::table('competition_season')->insert([
            [
                'instance_id' => $instance->id,
                'competition_id' => $competition->id,
                'season_id' => 1,
                'group_id' => 1,
                'club_id' => $beta->id,
                'points' => 6,
                'goals_for' => 5,
                'goals_against' => 3,
                'played' => 3,
                'wins' => 2,
                'draws' => 0,
                'losses' => 1,
            ],
            [
                'instance_id' => $instance->id,
                'competition_id' => $competition->id,
                'season_id' => 1,
                'group_id' => 1,
                'club_id' => $alpha->id,
                'points' => 6,
                'goals_for' => 7,
                'goals_against' => 3,
                'played' => 3,
                'wins' => 2,
                'draws' => 0,
                'losses' => 1,
            ],
            [
                'instance_id' => $instance->id,
                'competition_id' => $competition->id,
                'season_id' => 1,
                'group_id' => 2,
                'club_id' => $delta->id,
                'points' => 4,
                'goals_for' => 3,
                'goals_against' => 2,
                'played' => 3,
                'wins' => 1,
                'draws' => 1,
                'losses' => 1,
            ],
            [
                'instance_id' => $instance->id,
                'competition_id' => $competition->id,
                'season_id' => 1,
                'group_id' => 2,
                'club_id' => $charlie->id,
                'points' => 7,
                'goals_for' => 6,
                'goals_against' => 2,
                'played' => 3,
                'wins' => 2,
                'draws' => 1,
                'losses' => 0,
            ],
        ]);

        $response = $this
            ->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson("/api/competition/{$competition->id}/tournament-groups-tables");

        $response
            ->assertOk()
            ->assertJsonPath('data.1.0.position', 1)
            ->assertJsonPath('data.1.0.club_id', $alpha->id)
            ->assertJsonPath('data.1.0.club_name', 'Alpha FC')
            ->assertJsonPath('data.1.0.played', 3)
            ->assertJsonPath('data.1.0.wins', 2)
            ->assertJsonPath('data.1.0.draws', 0)
            ->assertJsonPath('data.1.0.losses', 1)
            ->assertJsonPath('data.1.0.goals_for', 7)
            ->assertJsonPath('data.1.0.goals_against', 3)
            ->assertJsonPath('data.1.0.goal_difference', 4)
            ->assertJsonPath('data.1.0.points', 6)
            ->assertJsonPath('data.1.1.position', 2)
            ->assertJsonPath('data.1.1.club_id', $beta->id)
            ->assertJsonPath('data.2.0.position', 1)
            ->assertJsonPath('data.2.0.club_id', $charlie->id)
            ->assertJsonPath('data.2.1.position', 2)
            ->assertJsonPath('data.2.1.club_id', $delta->id)
            ->assertJsonCount(2, 'data.1')
            ->assertJsonCount(2, 'data.2');
    }

    #[Test]
    public function it_returns_the_requested_competition_knockout_phase_summary(): void
    {
        $instance = Instance::factory()->create([
            'id' => 1,
            'instance_hash' => 'current-instance',
            'season_id' => 1,
        ]);
        Season::factory()->create([
            'id' => 1,
            'instance_id' => $instance->id,
        ]);
        $competition = Competition::factory()->create([
            'id' => 10,
            'instance_id' => $instance->id,
            'type' => 'tournament',
            'groups' => 0,
        ]);
        Competition::factory()->create([
            'id' => 11,
            'instance_id' => $instance->id,
            'type' => 'tournament',
            'groups' => 0,
        ]);

        DB::table('tournament_knockout')->insert([
            [
                'instance_id' => $instance->id,
                'competition_id' => 11,
                'season_id' => 1,
                'participant_count' => 8,
                'bracket_size' => 8,
                'status' => 'in_progress',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'instance_id' => $instance->id,
                'competition_id' => $competition->id,
                'season_id' => 1,
                'participant_count' => 16,
                'bracket_size' => 16,
                'status' => 'in_progress',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this
            ->withHeaders(['instanceHash' => 'current-instance'])
            ->getJson("/api/competition/{$competition->id}/knockout-phase");

        $response
            ->assertOk()
            ->assertJsonPath('data.competition_id', $competition->id)
            ->assertJsonPath('data.participant_count', 16);
    }
}
