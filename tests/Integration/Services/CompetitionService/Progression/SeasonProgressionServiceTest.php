<?php

namespace Tests\Integration\Services\CompetitionService\Progression;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use App\Services\CompetitionService\Progression\DomesticMovementService;
use App\Services\CompetitionService\Progression\SeasonProgressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeasonProgressionServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_calculates_balanced_movements_and_priority_based_continental_places_idempotently(): void
    {
        $data = $this->world();
        $service = app(SeasonProgressionService::class);

        $first = $service->finalize($data['season']->id);
        $second = $service->finalize($data['season']->id);

        $this->assertSame(['movements' => 4, 'qualifications' => 4], $first);
        $this->assertSame($first, $second);
        $this->assertDatabaseCount('club_competition_movements', 4);
        $this->assertDatabaseCount('club_competition_qualifications', 4);

        $this->assertDatabaseHas('club_competition_movements', [
            'club_id' => $data['upper_clubs'][4]->id,
            'from_competition_id' => $data['upper']->id,
            'to_competition_id' => $data['lower']->id,
            'type' => 'relegation',
            'source_position' => 5,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('club_competition_movements', [
            'club_id' => $data['lower_clubs'][0]->id,
            'from_competition_id' => $data['lower']->id,
            'to_competition_id' => $data['upper']->id,
            'type' => 'promotion',
            'source_position' => 1,
        ]);

        $this->assertDatabaseHas('club_competition_qualifications', [
            'club_id' => $data['upper_clubs'][0]->id,
            'target_competition_id' => $data['champions']->id,
            'source_position' => 1,
        ]);
        $this->assertDatabaseHas('club_competition_qualifications', [
            'club_id' => $data['upper_clubs'][2]->id,
            'target_competition_id' => $data['uefa']->id,
            'qualification_type' => 'competition_winner',
        ]);
        $this->assertDatabaseHas('club_competition_qualifications', [
            'club_id' => $data['upper_clubs'][3]->id,
            'target_competition_id' => $data['uefa']->id,
            'qualification_type' => 'league_position',
            'source_position' => 4,
        ]);
    }

    #[Test]
    public function a_postponed_game_blocks_finalization_but_not_a_live_movement_preview(): void
    {
        $data = $this->world();
        Game::factory()->create([
            'instance_id' => $data['instance']->id,
            'season_id' => $data['season']->id,
            'competition_id' => $data['upper']->id,
            'hometeam_id' => $data['upper_clubs'][0]->id,
            'awayteam_id' => $data['upper_clubs'][1]->id,
            'stadium_id' => $data['upper_clubs'][0]->stadium_id,
            'status' => Game::STATUS_POSTPONED,
        ]);

        $preview = app(DomesticMovementService::class)
            ->previewForCompetition($data['upper'], $data['season']->id);
        $this->assertCount(4, $preview);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('still has unresolved games');
        app(SeasonProgressionService::class)->finalize($data['season']->id);
    }

    #[Test]
    public function preview_endpoints_return_current_movement_and_qualification_projections(): void
    {
        $data = $this->world();

        $this->withHeaders(['instanceHash' => $data['instance']->instance_hash])
            ->getJson("/api/competition/{$data['upper']->id}/movement-preview")
            ->assertOk()->assertJsonCount(4, 'data');

        $this->withHeaders(['instanceHash' => $data['instance']->instance_hash])
            ->getJson("/api/competition/{$data['upper']->id}/qualification-preview")
            ->assertOk()->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.target_competition_id', $data['champions']->id);
    }

    private function world(): array
    {
        DB::table('base_competitions')->insert([
            ['id' => 101, 'name' => 'Top League', 'country_code' => 'GBR', 'rank' => 100, 'type' => 'league', 'groups' => null, 'clubs_number' => 6],
            ['id' => 102, 'name' => 'Lower League', 'country_code' => 'GBR', 'rank' => 50, 'type' => 'league', 'groups' => null, 'clubs_number' => 6],
            ['id' => 103, 'name' => 'Champions League', 'country_code' => 'EU', 'rank' => 200, 'type' => 'tournament', 'groups' => 1, 'clubs_number' => 32],
            ['id' => 104, 'name' => 'UEFA Cup', 'country_code' => 'EU', 'rank' => 150, 'type' => 'tournament', 'groups' => 0, 'clubs_number' => 32],
            ['id' => 105, 'name' => 'Domestic Cup', 'country_code' => 'GBR', 'rank' => 80, 'type' => 'tournament', 'groups' => 0, 'clubs_number' => 12],
        ]);
        DB::table('league_tier_rules')->insert([
            'upper_base_competition_id' => 101, 'lower_base_competition_id' => 102,
            'automatic_movement_places' => 2, 'active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('competition_qualification_rules')->insert([
            ['source_base_competition_id' => 101, 'target_base_competition_id' => 103, 'selector_type' => 'league_position', 'position_from' => 1, 'position_to' => 2, 'entry_stage' => 'group_stage', 'duplicate_policy' => 'next_league_position', 'priority' => 10, 'active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['source_base_competition_id' => 105, 'target_base_competition_id' => 104, 'selector_type' => 'competition_winner', 'position_from' => null, 'position_to' => null, 'entry_stage' => 'group_stage', 'duplicate_policy' => 'next_league_position', 'priority' => 15, 'active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['source_base_competition_id' => 101, 'target_base_competition_id' => 104, 'selector_type' => 'league_position', 'position_from' => 3, 'position_to' => 3, 'entry_stage' => 'group_stage', 'duplicate_policy' => 'next_league_position', 'priority' => 20, 'active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $instance = Instance::factory()->create(['id' => 1, 'season_id' => 1, 'instance_hash' => 'progression-instance']);
        $season = Season::factory()->create(['id' => 1, 'instance_id' => 1, 'start_date' => '2026-08-15', 'end_date' => '2027-08-15']);
        $upper = $this->competition(1, 101, 'Top League', 'GBR', 'league', null, 100);
        $lower = $this->competition(2, 102, 'Lower League', 'GBR', 'league', null, 50);
        $champions = $this->competition(3, 103, 'Champions League', 'EU', 'tournament', 1, 200);
        $uefa = $this->competition(4, 104, 'UEFA Cup', 'EU', 'tournament', 0, 150);
        $cup = $this->competition(5, 105, 'Domestic Cup', 'GBR', 'tournament', 0, 80);

        $upperClubs = $this->standings($upper, 1, 1000);
        $lowerClubs = $this->standings($lower, 7, 500);
        DB::table('tournament_knockout')->insert([
            'instance_id' => 1, 'competition_id' => $cup->id, 'season_id' => 1,
            'participant_count' => 12, 'bracket_size' => 16, 'status' => 'completed',
            'winner_club_id' => $upperClubs[0]->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return compact('instance', 'season', 'upper', 'lower', 'champions', 'uefa', 'cup', 'upperClubs', 'lowerClubs') + [
            'upper_clubs' => $upperClubs, 'lower_clubs' => $lowerClubs,
        ];
    }

    private function competition(int $id, int $baseId, string $name, string $country, string $type, ?int $groups, int $rank): Competition
    {
        return Competition::factory()->create(['id' => $id, 'instance_id' => 1, 'base_competition_id' => $baseId, 'name' => $name, 'country_code' => $country, 'type' => $type, 'groups' => $groups, 'rank' => $rank, 'clubs_number' => $type === 'league' ? 6 : 32]);
    }

    private function standings(Competition $competition, int $firstClubId, int $topPoints): array
    {
        $clubs = [];
        for ($offset = 0; $offset < 6; $offset++) {
            $club = Club::factory()->create(['id' => $firstClubId + $offset, 'instance_id' => 1, 'stadium_id' => 2000 + $firstClubId + $offset]);
            $clubs[] = $club;
            DB::table('competition_season')->insert([
                'instance_id' => 1, 'season_id' => 1, 'competition_id' => $competition->id,
                'club_id' => $club->id, 'points' => $topPoints - ($offset * 10),
                'goals_for' => 30 - $offset, 'goals_against' => 10 + $offset,
            ]);
        }

        return $clubs;
    }
}
