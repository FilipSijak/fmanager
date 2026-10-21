<?php

namespace App\Services\NewsService;

enum NewsType: string
{
    case Transfer = 'transfer';
    case Match = 'match';
    case Job = 'job';
    case Sponsorship = 'sponsorship';
}
