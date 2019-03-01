<?php

namespace App\GameEngine\Player\PlayerConfiguration;

class PlayerFieldsConfig
{
	const TEHNICAL_FIELDS = [
		'corners', 'crossing', 'dribbling', 'finishing', 'firstTouch', 'freeKick', 'heading', 'longShots', 'longThrows', 'marking', 'passing', 'penaltyTaking', 'tackling', 'technique'
	];

	const MENTAL_FIELDS = [
		'aggression', 'anticipation', 'bravery', 'composure', 'concentration', 'creativity', 'decisions', 'determination', 'flair', 'leadership', 'offTheBall', 'positioning', 'teamwork', 'workrate'
	];

	const PHYSICAL_FILDS = [
		'acceleration', 'agility', 'balance', 'jumping', 'naturalFitness', 'pace', 'stamina', 'strength'
	];
}