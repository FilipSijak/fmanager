<?php

namespace Tests\Unit\Competition;

use App\Models\Game;
use App\Models\Season;
use App\Repositories\CompetitionRepository;
use App\Services\CompetitionService\Competitions\Tournament;
use App\Services\CompetitionService\Competitions\TournamentUpdater;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TournamentTest extends TestCase
{
    use DatabaseMigrations;

    #[Test]
    public function it_can_create_groups_from_array_of_clubs()
    {
        $tournament = new Tournament();
        $clubs = [
            0 => ["id" => 1],
            1 => ["id" => 2],
            2 => ["id" => 3],
            3 => ["id" => 4],
            4 => ["id" => 5],
            5 => ["id" => 6],
            6 => ["id" => 7],
            7 => ["id" => 8],
        ];

        $clubsByGroups = $tournament->createTournamentGroups($clubs);

        $this->assertEquals(2, count($clubsByGroups));
        $this->assertEquals(4, count($clubsByGroups[1]));
    }

    #[Test]
    public function it_can_create_a_tournament()
    {
        $tournament = new Tournament();
        $clubs = [
            0 => (object)["id" => 1],
            1 => (object)["id" => 2],
            2 => (object)["id" => 3],
            3 => (object)["id" => 4],
            4 => (object)["id" => 5],
            5 => (object)["id" => 6],
            6 => (object)["id" => 7],
            7 => (object)["id" => 8],
        ];

        $summary = $tournament->createTournament($clubs);

        $this->assertEquals(2, $summary["first_group"]["num_rounds"]);
        $this->assertEquals(2, count($summary["first_group"]["rounds"][1]["pairs"]));
        $this->assertEquals(2, count($summary["second_group"]["rounds"][1]["pairs"]));
    }

    #[Test]
    public function it_can_update_tournament_summary()
    {
        $competitionRepoMock = $this->createMock(CompetitionRepository::class);
        $tournamentUpdater = new TournamentUpdater($competitionRepoMock);

        $tournamentStructure = new \stdClass();
        $summary = [
            "winner" => null,
            "finals_match" => null,
            "first_group" => [
                "num_rounds" => 1,
                "rounds" => [
                    1 => [
                        "pairs" => [
                            0 => (object) [
                                "match1" => (object) [
                                    "hometeamId" => 17,
                                    "awayTeamId" => 19
                                ],
                                "match2" => (object) [
                                    "hometeamId" => 19,
                                    "awayTeamId" => 17
                                ],
                                "winner" => null,
                                "match1Id" => 1,
                                "match2Id" => 3,
                            ]
                        ]
                    ]
                ]
            ],
            "second_group" => [
                "num_rounds" => 1,
                "rounds" => [
                    1 => [
                        "pairs" => [
                            0 => (object) [
                                "match1" => (object) [
                                    "hometeamId" => 3,
                                    "awayTeamId" => 4
                                ],
                                "match2" => (object) [
                                    "hometeamId" => 4,
                                    "awayTeamId" => 3
                                ],
                                "winner" => null,
                                "match1Id" => 4,
                                "match2Id" => 5,
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $tournamentStructure->summary = json_encode($summary);
        $tournamentStructure->id = 1;

        $competitionRepoMock->expects($this->once())->method('tournamentKnockoutStageByCompetitionId')->willReturn($tournamentStructure);

        Game::factory()->create(
            ['id' => 1, 'instance_id' => 1, 'season_id' => 1, 'competition_id' => 6, 'hometeam_id' => 17, 'awayteam_id' => 19, 'winner' => 1, 'stadium_id' => 1, 'match_summary' => '{}']
        );
        Game::factory()->create(
            ['id' => 3, 'instance_id' => 1, 'season_id' => 1, 'competition_id' => 6, 'hometeam_id' => 19, 'awayteam_id' => 17, 'winner' => 3, 'stadium_id' => 1, 'match_summary' => '{}']
        );
        Game::factory()->create(
            ['id' => 4, 'instance_id' => 1, 'season_id' => 1, 'competition_id' => 6, 'hometeam_id' => 18, 'awayteam_id' => 16, 'winner' => 1, 'stadium_id' => 1, 'match_summary' => '{}']
        );
        Game::factory()->create(
            ['id' => 5, 'instance_id' => 1, 'season_id' => 1, 'competition_id' => 6, 'hometeam_id' => 16, 'awayteam_id' => 18, 'winner' => 2, 'stadium_id' => 1, 'match_summary' => '{}']
        );
        Season::factory()->create(['id' => 1]);

        $tournamentUpdater->setInstanceId(1);
        $tournamentUpdater->setSeason(Season::where('id', 1)->first());
        $tournamentUpdater->updateTournamentSummary(Game::all()->toArray());
    }
}
