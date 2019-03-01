<?php

namespace App\GameEngine\Player\PlayerConfiguration;

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
        'forward' => array('technique', 'penaltyTaking'),
        'defending_middfielder' => array('passing', 'heading'),
        'creative_middfielder' => array('technique', 'dribbling'),
        'center_back' => array(),
        'wing_back' => array('marking', 'longThrows'),
        'winger' => array('firstTouch')
    ];
}