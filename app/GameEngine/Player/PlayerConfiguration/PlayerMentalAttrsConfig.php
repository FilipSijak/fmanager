<?php

namespace App\GameEngine\Player\PlayerConfiguration;

class PlayerMentalAttrsConfig
{
    const PRIMARY_MENTAL_ATTRIBUTES = [
        'forward' => array('anticipation', 'flair', 'composure'),
        'defending_middfielder' => array('workRate', 'determination'),
        'creative_middfielder' => array('creativity'),
        'center_back' => array('positioning', 'decisions'),
        'wing_back' => array('offTheBall', 'workRate'),
        'winger' => array('offTheBall')
    ];

    const SECONDARY_MENTAL_ATTRIBUTES = [
        'forward' => array('offTheBall'),
        'defending_middfielder' => array('teamWork', 'positioning'),
        'creative_middfielder' => array('flair','offTheBall'),
        'center_back' => array('composure', 'concentration', 'anticipation'),
        'wing_back' => array('positioning'),
        'winger' => array('flair', 'workRate')
    ];
}