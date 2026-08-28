<?php

return [
    'host' => env('ELASTICSEARCH_HOST', 'http://elasticsearch:9200'),
    'player_index' => env('ELASTICSEARCH_PLAYER_INDEX', 'fmanager_players'),
];
