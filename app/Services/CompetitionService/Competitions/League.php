<?php

namespace App\Services\CompetitionService\Competitions;

class League
{
    private array $clubs = [];
    private array $games = [];
    private int $fixed;
    private bool $home = true;
    private int $numberOfGamesInRound;
    private int   $competitionSize;

    /**
     * The tournament scheduling algorithm with the idea taken from the Berger tables in planning of tournaments
     *
     * Berger tables src: https://en.wikipedia.org/wiki/Round-robin_tournament#Scheduling_algorithm
     *
     * @param array $clubs
     * @return array
     */
    public function generateLeagueGames(array $clubs): array
    {

        $this->clubs                = array_values($clubs);
        $this->competitionSize      = count($this->clubs);
        $this->numberOfGamesInRound = $this->competitionSize / 2;

        if ($this->competitionSize % 2 !== 0 || $this->competitionSize > 20 || $this->competitionSize < 4) {
            throw new \UnexpectedValueException('Required: Min 4 clubs, max 20 clubs, even number. Competition size = '. $this->competitionSize);
        }

        $firstClub = 0;

        if (!empty($this->clubs)) {
            $firstKey = array_key_first($this->clubs);

            $firstClub = $this->clubs[$firstKey];
        }

        $this->fixed = $firstClub;

        // Creates first round
        for ($i = 0, $k = $this->competitionSize -1; $i < $k; $i++, $k--) {
            $game             = new \stdClass();
            $game->homeTeamId = $this->clubs[$i];
            $game->awayTeamId = $this->clubs[$k];

            $this->games[] = $game;
        }

        // Based on first round games, this will loop and set the rest of rounds
        for ($i = 0; $i < $this->competitionSize - 2; $i++) {
            $this->generateSingleRoundGames();
        }

        $this->swapTeamsForRematch();

        return $this->games;
    }

    private function generateSingleRoundGames(): void
    {
        $localGames = [];
        $this->home = !$this->home;
        $gamesCount = count($this->games);

        for ($i = $gamesCount - $this->numberOfGamesInRound, $k = $gamesCount - 1; $i < $gamesCount; $i++, $k--) {
            $game = new \stdClass();

            // first game in the round
            if ($i == $gamesCount - $this->numberOfGamesInRound) {
                if ($this->home) {
                    $game->homeTeamId = $this->fixed;
                    $game->awayTeamId = $this->games[$k]->awayTeamId;
                } else {
                    $game->homeTeamId = $this->games[$k]->awayTeamId;
                    $game->awayTeamId = $this->fixed;
                }

                $localGames[] = $game;
            } else {
                // when k is at the first 0 index of previous round
                if ($k == $gamesCount - $this->numberOfGamesInRound) {
                    // decides last game home/away team depending on where the fixed was (home or away)
                    if ($this->home) {
                        $game->homeTeamId = $this->games[$k]->homeTeamId;
                        $game->awayTeamId = $this->games[$k + 1]->homeTeamId;

                        $localGames[] = $game;
                    } else {
                        $game->homeTeamId = $this->games[$k]->awayTeamId;
                        $game->awayTeamId = $this->games[$k + 1]->homeTeamId;

                        $localGames[] = $game;
                    }

                    continue;
                }

                if ($k > 0) {
                    $game->homeTeamId = $this->games[$k]->awayTeamId;
                    $game->awayTeamId = $this->games[$k + 1]->homeTeamId;

                    $localGames[] = $game;
                }
            }
        }

        $this->games = array_merge($this->games, $localGames);
    }

    private function swapTeamsForRematch(): void
    {
        $rematchGames = [];

        foreach ($this->games as $game) {
            $rematch             = new \stdClass();
            $rematch->homeTeamId = $game->awayTeamId;
            $rematch->awayTeamId = $game->homeTeamId;

            $rematchGames[] = $rematch;
        }

        $this->games = array_merge($this->games, $rematchGames);
    }

}
