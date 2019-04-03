<?php

namespace App\GameEngine\Player\PlayerConfiguration;

/*
 * Player technical attributes
*/
class PlayerTechAttrsConfig
{
    const PRIMARY_TECH_ATTRIBUTES = [
        'forward' => array('finishing', 'dribbling', 'firstTouch'),
        'defending_middfielder' => array('tackling', 'marking'),
        'creative_middfielder' => array('passing', 'firstTouch'),
        'center_back' => array('tackling', 'heading', 'marking'),
        'wing_back' => array('crossing', 'tackling'),
        'winger' => array('dribbling', 'crossing', 'passing')
    ];

    const SECONDARY_TECH_ATTRIBUTES = [
        'forward' => array('technique', 'penalty_taking'),
        'defending_middfielder' => array('passing', 'heading'),
        'creative_middfielder' => array('technique', 'dribbling'),
        'center_back' => array(),
        'wing_back' => array('marking', 'long_throws'),
        'winger' => array('firstTouch')
    ];
}