<?php

namespace App;

enum GameEntityType: string
{
    case BANK = 'bank';
    case LOAN_SHARKS = 'loan_sharks';
    case SPONSOR = 'sponsor';
    case LEAGUE = 'league';
    case TV_BROADCASTER = 'tv_broadcaster';
    case COMPETITION = 'competition';
}
