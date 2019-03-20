<?php

namespace App\GameEngine\Player\PlayerConfiguration;

class PlayerMentalAttrsConfig
{
    const PRIMARY_MENTAL_ATTRIBUTES = [
        'forward' => array('anticipation', 'flair', 'composure'),
        'defending_middfielder' => array('workRate', 'determination'),
        'creative_middfielder' => array('creativity'),
        'center_back' => array('positioning', 'decisions'),
        'wing_back' => array('of_the_ball', 'workRate'),
        'winger' => array('of_the_ball')
    ];

    const SECONDARY_MENTAL_ATTRIBUTES = [
        'forward' => array('of_the_ball'),
        'defending_middfielder' => array('teamWork', 'positioning'),
        'creative_middfielder' => array('flair','of_the_ball'),
        'center_back' => array('composure', 'concentration', 'anticipation'),
        'wing_back' => array('positioning'),
        'winger' => array('flair', 'workRate')
    ];
}