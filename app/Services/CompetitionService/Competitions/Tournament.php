<?php

namespace App\Services\CompetitionService\Competitions;


use App\Models\Club;
use App\Models\Game;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class Tournament
{
    public function createTournament(Collection $clubs)
    {
        $clubsCount = count($clubs);
        $halfSize   = ($clubsCount / 2);
        $rounds     = 0;

        if ($halfSize < 2) {
            return;
        }

        $calcNumRounds = function ($n) use (&$calcNumRounds, &$rounds) {
            if ($n % 2 == 1) {
                return 1;
            }

            $rounds++;

            return $calcNumRounds($n / 2);
        };


        $calcNumRounds($halfSize);

        $this->summary = [
            "first_group"       => [
                "num_rounds" => $rounds,
                "rounds"     => [],
            ],
            "second_group"      => [
                "num_rounds" => $rounds,
                "rounds"     => [],
            ],
            "winner"            => null,
            "second_placed"     => null,
            "third_placed"      => null,
            "finals_match"      => null,
            "third_place_match" => null,
        ];

        for ($i = 1; $i <= $rounds; $i++) {
            $this->summary["first_group"]["rounds"][$i]  = ["pairs" => []];
            $this->summary["second_group"]["rounds"][$i] = ["pairs" => []];
        }

        for ($i = 0, $k = $clubsCount - 1; $i < $halfSize; $i++, $k--) {
            if (!isset($clubs[$i])) {
                dd($i);
            }
            $pair = $this->makePairMatches($clubs[$i]->id, $clubs[$k]->id);

            // half of the pairs go into one group, the other half into a second group
            if ($i < $halfSize / 2) {
                $this->summary["first_group"]["rounds"][1]["pairs"][] = $pair;
            } else {
                $this->summary["second_group"]["rounds"][1]["pairs"][] = $pair;
            }
        }

        return $this->summary;
    }

    public function setTournamentFixtures(array $schedule, int $competitionId, string $startDate)
    {
        $carbonDate = Carbon::parse($startDate);
        $firstGame  = $carbonDate->copy()->modify("next Tuesday");

        $firstRoundPairs = array_merge(
            $schedule["first_group"]["rounds"][1]["pairs"],
            $schedule["second_group"]["rounds"][1]["pairs"]
        );

        foreach ($firstRoundPairs as $pair) {
            $game = new Game();
            $rematch = new Game();

            $game->competition_id = $competitionId;
            $game->hometeam_id = $pair->match1->homeTeamId;
            $game->awayteam_id = $pair->match1->awayTeamId;
            $game->match_start = $firstGame;
            $game->stadium_id = Club::where('id', $pair->match1->homeTeamId)->first()->stadium_id;

            $rematch->competition_id = $competitionId;
            $rematch->hometeam_id = $pair->match2->homeTeamId;
            $rematch->awayteam_id = $pair->match2->awayTeamId;
            $rematch->match_start = $firstGame->copy()->addWeek();
            $rematch->stadium_id = Club::where('id', $pair->match2->homeTeamId)->first()->stadium_id;

            $game->save();
            $rematch->save();

            $pair->match1Id = $game->id;
            $pair->match2Id = $rematch->id;
        }

        return $schedule;
    }

    public function setNextRoundPairs(array $clubs): array
    {
        $clubsCount = count($clubs);
        $halfSize   = ($clubsCount / 2);
        $pairs      = [];

        for ($i = 0, $k = $clubsCount - 1; $i < $halfSize; $i++, $k--) {
            $pairs[] = $this->makePairMatches($clubs[$i], $clubs[$k]);
        }

        return $pairs;
    }

    public function createTournamentGroups(array $clubs)
    {
        $counter               = 0;
        $currentGroup          = 1;
        $clubsByGroup = [];

        $groups = [
            0  => 1,
            4  => 2,
            8  => 3,
            12 => 4,
            16 => 5,
            20 => 6,
        ];

        $groupCounter = 1;

        foreach ($clubs as $key => $club) {
            if ($key %4 == 0) {
                $currentGroup = $groupCounter;
                $clubsByGroup[$currentGroup] = [];
                $groupCounter++;
            }

            $clubsByGroup[$currentGroup][] = $clubs[$key]["id"];
        }

        foreach ($clubsByGroup as &$group) {
            shuffle($group);
        }

        return $clubsByGroup;
    }

    private function makePairMatches(int $firstTeamId, int $secondTeamId): \stdClass
    {
        $pair   = new \stdClass();
        $match1 = new \stdClass();
        $match2 = new \stdClass();

        $match1->homeTeamId = $firstTeamId;
        $match1->awayTeamId = $secondTeamId;
        $match2->homeTeamId = $secondTeamId;
        $match2->awayTeamId = $firstTeamId;

        $pair->match1   = $match1;
        $pair->match2   = $match2;
        $pair->winner   = null;
        $pair->match1Id = null;
        $pair->match2Id = null;

        return $pair;
    }
}
