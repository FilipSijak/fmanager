<?php

namespace App\Services\CompetitionService\Competitions;

use App\Models\Club;
use App\Models\Game;
use App\Models\Season;
use App\Models\Stadium;
use App\Repositories\CompetitionRepository;
use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use App\Services\GameService\GameService;
use Carbon\Carbon;

class TournamentUpdater
{
    private int    $instanceId;
    private Season $season;

    public function __construct(public CompetitionRepository $competitionRepository)
    {
        $this->competitionRepository = $competitionRepository;
    }

    public function setInstanceId(int $instanceId)
    {
        $this->instanceId = $instanceId;
    }

    public function setSeason(Season $season)
    {
        $this->season = $season;
    }

    public function updatePointsTable(array $games)
    {
        if ($this->competitionRepository->tournamentGroupsFinished($games[0])) {
            // update competition do be tournament
            // create tournament based on group points
            // create tournament knockout matches
            $competitionId = $games[0]["competition_id"];

            // decide which clubs process to knockout stage
            $topClubsByGroups =$this->competitionRepository->topClubsByTournamentGroup($competitionId);
            $knockoutClubs    = [];

            foreach ($topClubsByGroups as $club) {
                $club            = Club::find($club->id);
                $knockoutClubs[] = $club->id;
            }

            $tournament = new Tournament();
            $tournamentConfig = new TournamentConfig();
            $knockoutSchedule = $tournament->createTournament($knockoutClubs, $this->instanceId, $this->season->id);

            $schedule = $tournament->setTournamentFixtures($this->instanceId, $this->season->id, $knockoutSchedule, $competitionId, $this->season->start_date);


            $dataSource = new CompetitionDataSource();
            $dataSource->storeTournamentKnockoutSchedule($competitionId, $this->season->id, $schedule);
        } else {
            // foreach match, take winner/draw and find club/clubs in the tournament_groups table
            // update each winner/draw with points
            foreach ($games as $game) {
                // DRAW
                if ($game["winner"] == 3) {
                    // update both teams with a point
                    $this->competitionRepository->updateTournamentGroupsPoints(
                        $game["competition_id"],
                        $game["hometeam_id"],
                        1
                    );

                    $this->competitionRepository->updateTournamentGroupsPoints(
                        $game["competition_id"],
                        $game["awayteam_id"],
                        1
                    );
                } else {
                    // update points for the winning team

                    $winningTeamId = $game["winner"] == 1 ? $game["hometeam_id"] : $game["awayteam_id"];

                    $this->competitionRepository->updateTournamentGroupsPoints(
                        $game["competition_id"],
                        $winningTeamId,
                        3
                    );
                }
            }
        }
    }

    public function updateTournamentSummary(array $games)
    {
        $competitionId       = $games[0]['competition_id'];
        $tournamentStructure = $this->competitionRepository->tournamentKnockoutStageByCompetitionId($this->instanceId, $this->season->id, $competitionId);
        $summary             = json_decode($tournamentStructure->summary, true);

        $knockoutSummary  = new KnockoutSummary();
        $tournamentParser = new KnockoutSummaryParser();
        $tournamentParser->parseSchema($summary, $knockoutSummary);

        $lastRound = $summary["first_group"]["num_rounds"];

        if (
            !$summary["winner"] &&
            !$summary["finals_match"]
        ) {
            $summary["first_group"]["rounds"]  = $this->updateKnockoutGroup($knockoutSummary->getFirstGroup()["rounds"], $competitionId);
            $summary["second_group"]["rounds"] = $this->updateKnockoutGroup($knockoutSummary->getSecondGroup()["rounds"], $competitionId);
        }

        if (
            !$summary["winner"] &&
            isset($summary["first_group"]["rounds"][$lastRound]["pairs"][0]) &&
            isset($summary["second_group"]["rounds"][$lastRound]["pairs"][0]) &&
            $summary["first_group"]["rounds"][$lastRound]["pairs"][0]["winner"] &&
            !$summary["finals_match"]
        ) {
            $firstGroupPair  = $summary["first_group"]["rounds"][$lastRound]["pairs"][0];
            $secondGroupPair = $summary["second_group"]["rounds"][$lastRound]["pairs"][0];

            $firstGroupWinner  = $firstGroupPair["winner"];
            $secondGroupWinner = $secondGroupPair["winner"];

            // takes the last game of previous round to decide on the date of finals match
            $lastMatch  = Game::where('id', $firstGroupPair["match2Id"])->first();

            $finalsDate = Carbon::create($lastMatch->match_start)->addWeek(2);
            $finalsGame = new Game();

            $finalsGame->competition_id = $lastMatch->competition_id;
            $finalsGame->instance_id = $this->instanceId;
            $finalsGame->season_id = $this->season->id;
            $finalsGame->hometeam_id = $firstGroupWinner;
            $finalsGame->awayteam_id = $secondGroupWinner;
            $finalsGame->stadium_id = (Stadium::where('id', 1)->first())->id;
            $finalsGame->match_start = $finalsDate;

            $finalsGame->save();

            $summary["finals_match"] = $finalsGame->id;
        }

        if ($summary["finals_match"] == $games[0]["id"]) {
            $finalsMatch = $games[0];

            if ($finalsMatch["winner"] == 3) {
                $matchService = new GameService();
                $matchService->simulateMatchExtraTime($summary["finals_match"]);
            }

            $summary["winner"] = $finalsMatch["winner"] == 1 ? $finalsMatch["hometeam_id"] : $finalsMatch["awayteam_id"];
        }

        $this->competitionRepository->updateKnockoutSummary($summary, $tournamentStructure->id);
    }

    private function updateKnockoutGroup(array $tournamentGroup, int $competitionId)
    {
        $finishedMatches = $this->competitionRepository->finishedKnockoutMatches($competitionId);

        foreach ($finishedMatches as $match) {
            $matchesMapped[$match->id] = $match->id;
        }

        $winners = [];

        foreach ($tournamentGroup as $key => &$round) {
            $winners[$key] = [];

            if (!empty($round["pairs"])) {
                // already created pairs
                // will take winners from them and create new matches in else of those winners

                // going through each round that has assigned pairs and gives a winner
                foreach ($round["pairs"] as &$pair) {
                    if ($pair["winner"]) {
                        continue;
                    }
/*
                    echo "<pre>";
                    var_dump($matchesMapped);
                    var_dump($pair);
                    var_dump($matchesMapped[$pair["match1Id"]]);*/

                    if (isset($matchesMapped[$pair["match1Id"]]) && isset($matchesMapped[$pair["match2Id"]])) {
                        $winner = $this->competitionRepository->tournamentRoundWinner($pair["match1Id"], $pair["match2Id"]);

                        if (!$winner) {
                            break;
                        }

                        $pair["winner"]  = $winner;
                        $winners[$key][] = $winner;
                    } else {
                        // means that only one match has been played in each round so no need to go through all of them
                        // this will change once matches of the same round are played on different dates
                        //break;
                    }
                }
            } else {
                // creates new set of pairs after previous round has winners

                if (
                    (isset($tournamentGroup[$key - 1]) && $tournamentGroup[$key - 1]["pairs"][0]["winner"]) &&
                    empty($tournamentGroup[$key]["pairs"])
                ) {
                    $tournament = new Tournament();
                    $newMatches         = $tournament->setNextRoundPairs($winners[$key - 1]);
                    $newMatches         = json_decode(json_encode($newMatches), true);

                    $lastGame = Game::where('id', $tournamentGroup[$key - 1]["pairs"][0]["match2Id"])->first();
                    $this->createNewRoundMatches($newMatches, $lastGame);
                    $tournamentGroup[$key]["pairs"] = $newMatches;

                    break;
                } else {
                    // other rounds are still empty so no need to loop through them

                    break;
                }
            }
        }

        return $tournamentGroup;
    }

    private function createNewRoundMatches(array &$pairs, $lastMatch)
    {
        $firstMatchDate  = Carbon::create($lastMatch->match_start)->addWeek();

        foreach ($pairs as &$pair) {
            $game                = new Game();

            $game->hometeam_id = $pair["match1"]["homeTeamId"];
            $game->awayteam_id = $pair["match1"]["awayTeamId"];
            $game->stadium_id = (Stadium::where('id', $pair["match1"]["homeTeamId"])->first())->id;
            $game->match_start = $firstMatchDate;
            $game->instance_id = $this->instanceId;
            $game->season_id = $this->season->id;
            $game->competition_id = $lastMatch->competition_id;
            $game->save();

            $pair["match1Id"] = $game->id;

            $game2 = new Game();

            $game2->hometeam_id = $pair["match2"]["homeTeamId"];
            $game2->awayteam_id = $pair["match2"]["awayTeamId"];
            $game2->stadium_id = (Stadium::where('id', $pair["match2"]["homeTeamId"])->first())->id;
            $game2->match_start = $firstMatchDate->addWeek();
            $game2->instance_id = $this->instanceId;
            $game2->season_id = $this->season->id;
            $game2->competition_id = $lastMatch->competition_id;
            $game2->save();

            $pair["match2Id"] = $game2->id;
        }
    }
}
