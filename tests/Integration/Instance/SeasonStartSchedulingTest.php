<?php

namespace Tests\Integration\Instance;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Instance;
use App\Models\Season;
use App\Services\SeasonService\SeasonStartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SeasonStartSchedulingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_schedules_every_supported_competition_format_idempotently(): void
    {
        $instance = Instance::factory()->create([
            'id' => 1,
            'season_id' => 1,
            'instance_date' => '2027-06-16',
        ]);
        $season = Season::factory()->create([
            'id' => 1,
            'instance_id' => $instance->id,
            'start_date' => '2027-08-15',
            'end_date' => '2028-06-15',
        ]);
        $league = $this->competition(1, 'League', 'league', null);
        $groupTournament = $this->competition(2, 'Group Tournament', 'tournament', 1);
        $knockoutCup = $this->competition(3, 'Knockout Cup', 'tournament', 0);

        $clubs = collect();
        for ($id = 1; $id <= 4; $id++) {
            $clubs->push(Club::factory()->create([
                'id' => $id,
                'instance_id' => $instance->id,
                'stadium_id' => 1000 + $id,
            ]));
        }

        foreach ([$league, $groupTournament, $knockoutCup] as $competition) {
            foreach ($clubs as $club) {
                DB::table('competition_season')->insert([
                    'instance_id' => $instance->id,
                    'season_id' => $season->id,
                    'competition_id' => $competition->id,
                    'club_id' => $club->id,
                ]);
            }
        }

        $service = app(SeasonStartService::class);
        $service->start($instance);
        $service->start($instance);

        $this->assertSame(12, DB::table('games')->where('competition_id', $league->id)->count());
        $this->assertSame(12, DB::table('games')->where('competition_id', $groupTournament->id)->count());
        $this->assertSame(4, DB::table('games')->where('competition_id', $knockoutCup->id)->count());
        $this->assertSame(28, DB::table('games')->count());
        $this->assertSame(
            4,
            DB::table('competition_season')
                ->where('competition_id', $groupTournament->id)
                ->whereNotNull('group_id')
                ->count()
        );
        $this->assertDatabaseHas('tournament_knockout', [
            'instance_id' => $instance->id,
            'season_id' => $season->id,
            'competition_id' => $knockoutCup->id,
            'participant_count' => 4,
            'status' => 'in_progress',
        ]);
        $this->assertSame(
            4,
            DB::table('games')
                ->where('competition_id', $knockoutCup->id)
                ->whereNotNull('knockout_tie_id')
                ->count()
        );
    }

    private function competition(int $id, string $name, string $type, ?int $groups): Competition
    {
        return Competition::factory()->create([
            'id' => $id,
            'instance_id' => 1,
            'name' => $name,
            'type' => $type,
            'groups' => $groups,
            'clubs_number' => 4,
        ]);
    }
}
