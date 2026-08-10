<?php

namespace Tests\Integration\Services\CompetitionService\Progression;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use App\Services\CompetitionService\Progression\CompetitionProgressionCalculator;
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
    public function it_calculates_all_progression_types_into_one_table_idempotently(): void
    {
        $data = $this->world();
        $service = app(SeasonProgressionService::class);

        $first = $service->finalize($data['season']->id);
        $second = $service->finalize($data['season']->id);

        $this->assertSame(['movements' => 4, 'qualifications' => 5], $first);
        $this->assertSame($first, $second);
        $this->assertDatabaseCount('club_competition_progressions', 9);
        $this->assertDatabaseHas('club_competition_progressions', [
            'club_id' => $data['upper_clubs'][4]->id,
            'source_competition_id' => $data['upper']->id,
            'target_competition_id' => $data['lower']->id,
            'progression_type' => 'relegation',
            'source_position' => 5,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('club_competition_progressions', [
            'club_id' => $data['lower_clubs'][0]->id,
            'source_competition_id' => $data['lower']->id,
            'target_competition_id' => $data['upper']->id,
            'progression_type' => 'promotion',
            'source_position' => 1,
        ]);
        $this->assertDatabaseHas('club_competition_progressions', [
            'club_id' => $data['upper_clubs'][0]->id,
            'target_competition_id' => $data['champions']->id,
            'progression_type' => 'continental',
            'source_position' => 1,
        ]);
        $this->assertDatabaseHas('club_competition_progressions', [
            'club_id' => $data['upper_clubs'][2]->id,
            'source_competition_id' => $data['cup']->id,
            'target_competition_id' => $data['uefa']->id,
            'progression_type' => 'continental',
        ]);
        $this->assertDatabaseHas('club_competition_progressions', [
            'club_id' => $data['upper_clubs'][3]->id,
            'source_competition_id' => $data['upper']->id,
            'target_competition_id' => $data['uefa']->id,
            'progression_type' => 'continental',
            'source_position' => 4,
        ]);
        $this->assertDatabaseHas('club_competition_progressions', [
            'club_id' => $data['upper_clubs'][4]->id,
            'target_competition_id' => $data['intertoto']->id,
            'progression_type' => 'continental',
            'source_position' => 5,
            'entry_stage' => 'qualifying',
        ]);
    }

    #[Test]
    public function a_postponed_game_blocks_finalization_but_not_a_live_preview(): void
    {
        $data = $this->world();
        Game::factory()->create([
            'instance_id' => 1,
            'season_id' => 1,
            'competition_id' => $data['upper']->id,
            'hometeam_id' => $data['upper_clubs'][0]->id,
            'awayteam_id' => $data['upper_clubs'][1]->id,
            'stadium_id' => $data['upper_clubs'][0]->stadium_id,
            'status' => Game::STATUS_POSTPONED,
        ]);

        $preview = app(CompetitionProgressionCalculator::class)->previewForCompetition(
            $data['upper'], 1, ['promotion', 'relegation']
        );
        $this->assertCount(4, $preview);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('still has unresolved games');
        app(SeasonProgressionService::class)->finalize(1);
    }

    #[Test]
    public function preview_endpoints_use_the_unified_calculator(): void
    {
        $data = $this->world();

        $this->withHeaders(['instanceHash' => $data['instance']->instance_hash])
            ->getJson("/api/competition/{$data['upper']->id}/movement-preview")
            ->assertOk()->assertJsonCount(4, 'data');
        $this->withHeaders(['instanceHash' => $data['instance']->instance_hash])
            ->getJson("/api/competition/{$data['upper']->id}/qualification-preview")
            ->assertOk()->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.target_competition_id', $data['champions']->id);
    }

    private function world(): array
    {
        DB::table('base_competitions')->insert([
            ['id' => 101, 'name' => 'Top League', 'country_code' => 'GBR', 'rank' => 100, 'type' => 'league', 'groups' => null, 'clubs_number' => 6, 'competition_scope' => 'domestic', 'continent' => null, 'continental_tier' => null],
            ['id' => 102, 'name' => 'Lower League', 'country_code' => 'GBR', 'rank' => 50, 'type' => 'league', 'groups' => null, 'clubs_number' => 6, 'competition_scope' => 'domestic', 'continent' => null, 'continental_tier' => null],
            ['id' => 103, 'name' => 'Champions League', 'country_code' => 'EU', 'rank' => 200, 'type' => 'tournament', 'groups' => 1, 'clubs_number' => 32, 'competition_scope' => 'continental', 'continent' => 'Europe', 'continental_tier' => 1],
            ['id' => 104, 'name' => 'UEFA Cup', 'country_code' => 'EU', 'rank' => 150, 'type' => 'tournament', 'groups' => 0, 'clubs_number' => 32, 'competition_scope' => 'continental', 'continent' => 'Europe', 'continental_tier' => 2],
            ['id' => 105, 'name' => 'Domestic Cup', 'country_code' => 'GBR', 'rank' => 80, 'type' => 'tournament', 'groups' => 0, 'clubs_number' => 12, 'competition_scope' => 'domestic', 'continent' => null, 'continental_tier' => null],
            ['id' => 106, 'name' => 'Intertoto Cup', 'country_code' => 'EU', 'rank' => 100, 'type' => 'tournament', 'groups' => 0, 'clubs_number' => 32, 'competition_scope' => 'continental', 'continent' => 'Europe', 'continental_tier' => 3],
        ]);
        DB::table('competition_progression_rules')->insert([
            $this->rule(101, 102, 'relegation', 'bottom_positions', null, null, 2, null, 10),
            $this->rule(102, 101, 'promotion', 'position_range', 1, 2, null, null, 10),
            $this->rule(101, 103, 'continental', 'position_range', 1, 2, null, 'group_stage', 10),
            $this->rule(105, 104, 'continental', 'competition_winner', null, null, null, 'group_stage', 15),
            $this->rule(101, 104, 'continental', 'position_range', 3, 3, null, 'group_stage', 20),
            $this->rule(101, 106, 'continental', 'position_range', 4, 4, null, 'qualifying', 30),
        ]);

        $instance = Instance::factory()->create(['id' => 1, 'season_id' => 1, 'instance_hash' => 'progression-instance']);
        $season = Season::factory()->create(['id' => 1, 'instance_id' => 1, 'start_date' => '2026-08-15', 'end_date' => '2027-08-15']);
        $upper = $this->competition(1, 101, 'Top League', 'GBR', 'league', null, 100);
        $lower = $this->competition(2, 102, 'Lower League', 'GBR', 'league', null, 50);
        $champions = $this->competition(3, 103, 'Champions League', 'EU', 'tournament', 1, 200);
        $uefa = $this->competition(4, 104, 'UEFA Cup', 'EU', 'tournament', 0, 150);
        $intertoto = $this->competition(6, 106, 'Intertoto Cup', 'EU', 'tournament', 0, 100);
        $cup = $this->competition(5, 105, 'Domestic Cup', 'GBR', 'tournament', 0, 80);
        $upperClubs = $this->standings($upper, 1, 1000);
        $lowerClubs = $this->standings($lower, 7, 500);
        DB::table('tournament_knockout')->insert([
            'instance_id' => 1, 'competition_id' => $cup->id, 'season_id' => 1,
            'participant_count' => 12, 'bracket_size' => 16, 'status' => 'completed',
            'winner_club_id' => $upperClubs[0]->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return compact('instance', 'season', 'upper', 'lower', 'champions', 'uefa', 'intertoto', 'cup') + [
            'upper_clubs' => $upperClubs,
            'lower_clubs' => $lowerClubs,
        ];
    }

    private function rule(int $source, int $target, string $type, string $selector, ?int $from, ?int $to, ?int $places, ?string $stage, int $priority): array
    {
        return [
            'source_base_competition_id' => $source, 'target_base_competition_id' => $target,
            'progression_type' => $type, 'selector_type' => $selector,
            'position_from' => $from, 'position_to' => $to, 'places' => $places,
            'entry_stage' => $stage, 'duplicate_policy' => 'next_league_position',
            'priority' => $priority, 'active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ];
    }

    private function competition(int $id, int $baseId, string $name, string $country, string $type, ?int $groups, int $rank): Competition
    {
        return Competition::factory()->create([
            'id' => $id, 'instance_id' => 1, 'base_competition_id' => $baseId,
            'name' => $name, 'country_code' => $country, 'type' => $type,
            'groups' => $groups, 'rank' => $rank, 'clubs_number' => $type === 'league' ? 6 : 32,
            'competition_scope' => $country === 'EU' ? 'continental' : 'domestic',
            'continent' => $country === 'EU' ? 'Europe' : null,
            'continental_tier' => match ($baseId) {
                103 => 1, 104 => 2, 106 => 3, default => null
            },
        ]);
    }

    private function standings(Competition $competition, int $firstClubId, int $topPoints): array
    {
        $clubs = [];
        for ($offset = 0; $offset < 6; $offset++) {
            $club = Club::factory()->create([
                'id' => $firstClubId + $offset, 'instance_id' => 1,
                'stadium_id' => 2000 + $firstClubId + $offset,
            ]);
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
