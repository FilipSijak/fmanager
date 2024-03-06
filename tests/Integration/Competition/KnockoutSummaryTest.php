<?php

namespace Tests\Integration\Competition;

use App\Models\Club;
use App\Models\Game;
use App\Models\Stadium;
use App\Repositories\GameRepository;
use App\Services\CompetitionService\Competitions\KnockoutSummaryRoundsData;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use function GuzzleHttp\Promise\all;

class KnockoutSummaryTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    public function itChecksGameInfoForTheCurrentRound()
    {
        (new DatabaseSeeder())->run();

        Club::factory()
            ->count(4)
            ->sequence(function (Sequence $sequence) {
                return [
                    'id' => $sequence->index + 1,
                    'name' => 'ClubName' . $sequence->index + 1
                ];
            })
            ->create();

        Stadium::factory()
            ->count(4)
            ->sequence(function (Sequence $sequence) {
                return [
                    'id' => $sequence->index + 1,
                    'name' => 'StadiumName' . $sequence->index + 1,
                    'instance_id' => 1
                ];
            })
            ->create();

        Game::factory()
            ->count(4)
            ->sequence(function (Sequence $sequence) {
                $odd = (($sequence->index + 1) % 2 != 0);

                return [
                    'id' => $sequence->index + 1,
                    'hometeam_id' => $odd ? $sequence->index + 1 : $sequence->index + 1,
                    'awayteam_id' => $odd ? $sequence->index + 2 : $sequence->index,
                    'stadium_id' => $sequence->index + 1,
                    'instance_id' => 1,
                    'season_id' => 1,
                    'competition_id' => 1
                ];
            })
            ->create();

        $gameRepository = new GameRepository();
        $gameRepository->setSeasonId(1);
        $gameRepository->setInstanceId(1);

        $knockoutSummaryParser = new KnockoutSummaryRoundsData($gameRepository);
        $summary = file_get_contents(__DIR__ . '/../../fixtures/knockoutSummaryGameInfoForRound.json');

        $actualResult = $knockoutSummaryParser->displayCurrentRound($summary);
        $expectedResult = [
            0 => [
                'game1' => [
                    'match_start' => Carbon::now()->format('Y-m-d H:m:00'),
                    'winner' => null,
                    'home_team_goals' => null,
                    'away_team_goals' => null,
                    'stadium_name' => 'StadiumName1',
                    'home_team' => 'ClubName1',
                    'away_team' => 'ClubName2'
                ],
                'game2' => [
                    'match_start' => Carbon::now()->format('Y-m-d H:m:00'),
                    'winner' => null,
                    'home_team_goals' => null,
                    'away_team_goals' => null,
                    'stadium_name' => 'StadiumName2',
                    'home_team' => 'ClubName2',
                    'away_team' => 'ClubName1'
                ],
                'winner' => null,
            ],
            1 => [
                'game1' => [
                    'match_start' => Carbon::now()->format('Y-m-d H:m:00'),
                    'winner' => null,
                    'home_team_goals' => null,
                    'away_team_goals' => null,
                    'stadium_name' => 'StadiumName3',
                    'home_team' => 'ClubName3',
                    'away_team' => 'ClubName4'
                ],
                'game2' => [
                    'match_start' => Carbon::now()->format('Y-m-d H:m:00'),
                    'winner' => null,
                    'home_team_goals' => null,
                    'away_team_goals' => null,
                    'stadium_name' => 'StadiumName4',
                    'home_team' => 'ClubName4',
                    'away_team' => 'ClubName3',
                ],
                'winner' => null,
            ]
        ];

        $this->assertEquals($expectedResult, $actualResult);
    }
}
