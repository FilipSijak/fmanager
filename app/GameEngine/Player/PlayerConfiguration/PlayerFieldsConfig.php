<?php

namespace App\GameEngine\Player\PlayerConfiguration;

/*
 * All the attributes that players can have devided in groups
*/
class PlayerFieldsConfig
{
    const TEHNICAL_FIELDS = [
        'corners', 'crossing', 'dribbling', 'finishing', 'first_touch', 'freeKick', 'heading', 'long_shots', 'long_throws', 'marking', 'passing', 'penalty_taking', 'tackling', 'technique'
    ];

    const MENTAL_FIELDS = [
        'aggression', 'anticipation', 'bravery', 'composure', 'concentration', 'creativity', 'decisions', 'determination', 'flair', 'leadership', 'of_the_ball', 'positioning', 'teamwork', 'workrate'
    ];

    const PHYSICAL_FILDS = [
        'acceleration', 'agility', 'balance', 'jumping', 'natural_fitness', 'pace', 'stamina', 'strength'
    ];
}