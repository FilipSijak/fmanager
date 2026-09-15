<?php

namespace App;

enum EntityTransactionDirection: string
{
    case ENTITY_TO_CLUB = 'entity_to_club';
    case CLUB_TO_ENTITY = 'club_to_entity';
}
