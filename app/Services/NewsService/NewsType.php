<?php

namespace App\Services\NewsService;

enum NewsType: string
{
    case Transfer = 'transfer';
    case Job = 'job';
}
