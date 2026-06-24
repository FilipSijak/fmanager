<?php

namespace Tests\Integration\Services\CompetitionService;

use App\Models\Club;
use App\Models\Season;
use App\Repositories\CompetitionRepository;
use App\Services\CompetitionService\CompetitionService;
use App\Services\CompetitionService\Competitions\LeagueUpdater;
use App\Services\CompetitionService\Competitions\TournamentUpdater;
use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompetitionServiceMakeLeagueTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_a_full_league_schedule_for_twenty_clubs(): void
    {
        $season = Season::factory()->create([
            'instance_id' => 1,
            'start_date' => '2026-08-15',
            'end_date' => '2027-08-15',
        ]);

        $clubIds = $this->createClubIds(20, 1);

        $this->makeService()->makeLeague($clubIds, 10, $season->id, 1);

        $this->assertDatabaseCount('games', 380);
        $this->assertDatabaseHas('games', [
            'instance_id' => 1,
            'season_id' => $season->id,
            'competition_id' => 10,
        ]);
    }

    #[Test]
    #[DataProvider('invalidClubCounts')]
    public function it_throws_when_club_count_is_not_twenty(int $clubCount): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('League schedule requires exactly 20 clubs');

        $this->makeService()->makeLeague(range(1, $clubCount), 10, 1, 1);
    }

    public static function invalidClubCounts(): array
    {
        return [
            'no clubs' => [0],
            'nineteen clubs' => [19],
            'twenty one clubs' => [21],
        ];
    }

    #[Test]
    public function it_throws_when_a_home_club_has_no_stadium_for_the_instance(): void
    {
        $season = Season::factory()->create([
            'instance_id' => 1,
            'start_date' => '2026-08-15',
            'end_date' => '2027-08-15',
        ]);

        $clubIds = $this->createClubIds(19, 1);
        $clubIds[] = Club::factory()->create([
            'instance_id' => 2,
            'stadium_id' => 999,
        ])->id;

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('has no stadium for instance 1');

        $this->makeService()->makeLeague($clubIds, 10, $season->id, 1);
    }

    private function createClubIds(int $count, int $instanceId): array
    {
        $clubIds = [];

        for ($i = 1; $i <= $count; $i++) {
            $clubIds[] = Club::factory()->create([
                'instance_id' => $instanceId,
                'stadium_id' => 1000 + $i,
            ])->id;
        }

        return $clubIds;
    }

    private function makeService(): CompetitionService
    {
        $dataSource = new CompetitionDataSource();
        $repository = new CompetitionRepository($dataSource);

        return new CompetitionService(
            new LeagueUpdater($repository),
            new TournamentUpdater($repository),
            $dataSource
        );
    }
}
