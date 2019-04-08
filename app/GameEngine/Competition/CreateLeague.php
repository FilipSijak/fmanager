<?php

namespace App\GameEngine\Competition;

use App\GameEngine\Competition\AbstractGenerateCompetition;

class CreateLeague extends AbstractGenerateCompetition
{
    private $number_of_clubs;
    private $clubs = [];
    private $gamesUnsorted = [];
    private $gamesFirstHalf = [];
    private $gamesSecondHalf = [];
    private $flag = true;

    public function __construct($number_of_clubs)
    {
        $this->number_of_clubs = $number_of_clubs;
        $this->generateclubs();
        $this->setAllGames();
        $this->generateRound();
    }

    public function generateRound()
    {
        $clubs = $this->clubs;
        $clubs_used = [];
        $data = [];
        $round = $this->gamesFirstHalf;
        //printArray($this->gamesFirstHalf);
        //$this->flag = !$this->flag;
        if ($this->flag) {
            for ($i = 0; $i < $round; $i++) {
                if (count($clubs) < 2) {
                    break;
                }
                $game = $round[$i];
                $data[] = $game;

                $child_scope = $this->filterClubsInRound($game->club_home, $game->club_away);
                $clubs = array_filter($clubs, $child_scope);
                printArray($clubs);
                exit();
                $clubs_used[] = $game->club_home;
                $clubs_used[] = $game->club_away;

                foreach ($round as $game2 => $val) {
                    if ( in_array($val->club_home, $clubs_used) || in_array($val->club_away, $clubs_used)) {
                        unset($round[$game2]);
                    }
                }
            }
        } else {
            foreach ($this->gamesSecondHalf as $game) {

            }
        }

        printArray($this->gamesFirstHalf);
        printArray($data);
    }

    private function filterClubsInRound($club_home, $club_away)
    {
        return function($term) use($club_home, $club_away) {
            return !($term->id == $club_home || $term->id == $club_away);
        };
    }

    /*private function checkIfClubOnTheList($clubs, $club_home, $club_away)
    {
        return function($term) use($clubs, $club_home, $club_away) {

            return !($term->id == $club_home || $term->id == $club_away);
        };
    }*/

    public function getRounds()
    {

    }

    private function setAllGames()
    {
        $game_id = 0;

        for ($i = 0; $i < $this->number_of_clubs; $i++) {
            if (!isset($this->clubs[$i + 1])) {
                break;
            }

            for ($k = $i; $k < $this->number_of_clubs; $k++) {
                if ($i == $k) {
                    continue;
                }
                $game_id++;
                $game = [];
                $game['id'] = $game_id;
                $game['club_home'] = $this->clubs[$i]->id;
                $game['club_away'] = $this->clubs[$k]->id;
                $game['name'] = $this->clubs[$i]->name . ' vs ' . $this->clubs[$k]->name . ' at ' . $this->clubs[$i]->stad;

                $this->gamesUnsorted[] = (object) $game;

                $game2 = [];
                $game2['club_home'] = $this->clubs[$k]->id;
                $game2['club_away'] = $this->clubs[$i]->id;
                $game2['name'] = $this->clubs[$k]->name . ' vs ' . $this->clubs[$i]->name . ' at ' . $this->clubs[$k]->stad;
                $this->gamesUnsorted[] = (object) $game2;
            }
        }

        foreach ($this->gamesUnsorted as $key => $game) {
            if ($key % 2 == 0) {
                $this->gamesFirstHalf[] = $game;
            } else {
                $this->gamesSecondHalf[] = $game;
            }
        }

        /*printArray($this->gamesFirstHalf);
        printArray($this->gamesSecondHalf);*/
    }

    private function generateclubs()
    {
        for ($i = 1; $i <= $this->number_of_clubs; $i++) {
            $club = [];
            $club['id'] = $i;
            $club['name'] = 'clb' . ($i);
            $club['stad'] = 'stdm' . ($i);
            $this->clubs[] = (object) $club;
        }
    }
}

$league = new League(4);
/*$league->generateRounds();
$rounds = $league->getRounds();
printArray($rounds);*/