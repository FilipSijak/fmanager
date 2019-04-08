<?php

namespace App\GameEngine\Competition;

use App\GameEngine\Competition\AbstractGenerateCompetition;

class CreateTournament extends AbstractGenerateCompetition
{
	private $gms;
	private $rcounter = 0;
	private $rounds = [];
	private $clbs = [];

	public function generate($numberOfClubs)
	{
		$clubs = [];

		for ($i = 0; $i <= $numberOfClubs; $i++) {
			$club = [];
			$club['id'] = $i + 1;
			$club['name'] = 'clb' . ($i + 1);
			$club['stad'] = 'stdm' . ($i + 1);
			$this->clbs[] = (object) $club;
		}

		$clubs_not_reversed = [];
		$games = [];

		for ($i = 0; $i < $numberOfClubs; $i++) {
			if (!isset($clbs[$i + 1])) {
				break;
			}

			for ($k = $i; $k < $numberOfClubs; $k++) {
				if ($i == $k) {
					continue;
				}

				$games[] = $clbs[$i]->name . ' vs ' . $clbs[$k]->name . ' at ' . $clbs[$i]->stad;
				$games[] = $clbs[$k]->name . ' vs ' . $clbs[$i]->name . ' at ' . $clbs[$k]->stad;
			}
		}

		$games_half1 = [];
		$games_half2 = [];

		foreach ($games as $key => $game) {
			if ($key % 2 == 0) {
				$games_half1[] = $game;
			} else {
				$games_half2[] = $game;
			}
		}

		$this->printArray($games_half1);
		$this->printArray($games_half2);
	}

	public function generateRounds($numberOfClubs)
	{
		$clubs = [];
		$games = [];
		$pairs = [];

		for ($i = 0; $i <= $numberOfClubs; $i++) {
			$club = [];
			$club['id'] = $i + 1;
			$club['name'] = 'club' . ($i + 1);
			$club['stadium'] = 'stadium' . ($i + 1);
			$clubs[] = (object) $club;
		}

		shuffle($clubs);

		for ($i = 0; $i <= $numberOfClubs; $i +=2) {
			if (!isset($clubs[$i + 1])) {
				break;
			}

			$games[] = $clubs[$i]->name . ' vs ' . $clubs[$i+1]->name . ' at ' . $clubs[$i]->stadium;
			$games[] = $clubs[$i+1]->name . ' vs ' . $clubs[$i]->name . ' at ' . $clubs[$i+1]->stadium;
		}

		$this->printArray($games);
	}
}
