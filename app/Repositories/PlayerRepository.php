<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use App\Player;

class PlayerRepository
{
	protected $model;

	public function __construct(Model $model = null)
	{
		if (!$model) {
			$model = new Player();
		}

		$this->model = $model;
	}

	public function setPlayerInitialInfo()
	{
		$data = $this->extractPlayersInfo();
		$end = count($data);
		return $data[rand(1, $end)];
	}

	private function extractPlayersInfo(): array
	{
		$rows = [];
		$count = 0;

		if (($handle = fopen(__DIR__ ."/mock_data.csv", "r")) !== FALSE) {
		    while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
		    	if (!$count) {
		    		$count++;
					continue;
				}

				$data = [];
				$data['first_name'] = $row[0];
				$data['last_name'] = $row[1];
				$data['age'] = rand(16, 37);
				$data['country'] = 1;  //needs function
		        $rows[] = $data;
		    }
		    fclose($handle);
		}

		return $rows;
	}

	public function getAverageGradeForSelectedPeriod(array $params):array
	{
		$query = "
			SELECT r1.*, month(games.created_at) as mnth
			FROM (
				SELECT grade_id, game_id FROM game_player
				WHERE player_id = " . $this->model->id . "
			) AS r1
			INNER JOIN games ON(games.id = r1.game_id)
			WHERE 1=1"
		;

		if (isset($params['interval'])) {
			$query .= " AND YEAR(games.created_at) BETWEEN " . $params['interval']['start'] . ' AND ' . $params['interval']['end'];
		}

		if (isset($params['year'])) {
			$query .= " AND YEAR(games.created_at) = " . $params['year'];
		}

		if (isset($params['month'])) {
			$query .= " AND MONTH(games.created_at) = " . $params['month'];
		}

		return DB::select($query);
	}
}
