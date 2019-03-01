<?php

namespace App\GameEngine\Player;

class PlayerPotential
{
	const POTENTIAL_BOUNDERIES = [0, 50, 75, 100, 130, 160, 180, 200];

	// Creates random value for tehnical, mental and physical potential
	// returns object with each value
	public static function calculatePlayerPotential($coef)
	{
		$playerPotential = new \stdClass();
		$playerAttributesCategories = ['technical', 'mental', 'physical'];

		for ($i = 0; $i < 3; $i++) {
			for ($k = 1; $k < count(self::POTENTIAL_BOUNDERIES); $k++) {
				if ($coef < self::POTENTIAL_BOUNDERIES[$k] && $coef > self::POTENTIAL_BOUNDERIES[$k - 1]) {
					$potentialValue = rand(self::POTENTIAL_BOUNDERIES[$k - 1], self::POTENTIAL_BOUNDERIES[$k]);
				}
			}

			$playerPotential->{$playerAttributesCategories[$i]} = $potentialValue;
		}

		return $playerPotential;
	}

	public static function playerPotentialLabel($potential)
	{
		$labels = [
			'amateur' => 50,
			'low' => 75,
			'professional' => 100,
			'normal' => 130,
			'high' => 160,
			'very_high' => 180,
			'world_class' => 200,
		];

		$labels_flipped = array_flip($labels);

		foreach ($labels as $label_coeficient) {
			if ($potential <= $label_coeficient) {
				return $labels_flipped[$label_coeficient];
			}
		}
	}
}

// 20 klubova = 200 igraca godisnje

// plan 1 lottery
/*
	Percentage for chance that some club will develop world class talent or some other talent category
	200 talent points

	amateur (0-50) - 
	low (51-75) -
	professional (76-100) -
	normal (101-130) - 
	high (131-160) -
	very_high (161-180) -
	world_class(181-200) - 

	Player will fall into category depending on various parameters such as club training ground, coaching, country talent etc
	Highet parameters are, better coeficient for random pull is

	amateur (1-20)
	low (21-40)
	professional (41-60)
	normal (61-70)
	high (71-85)
	very_high (85-97)
	world_class (98-100)
*/
/*
Ako klub ima veci koeficijent od 50 u tom slucaju nece radit amateur igrace i kod random generiranja range za random ce bit npr od 50 do
Klub ce u tom slucaju moci imat koeficijent od 1-99 ako ima 99 tada su sanse 100% da ce proizvest world_class igraca, zato oprezno s time
Uz to, za svakih 10 bodova ce se smanjit ulaz u world class za 1 bod i pomaknut ce se granice ispod

100 - 1
97 - 2
85 - 3

*/