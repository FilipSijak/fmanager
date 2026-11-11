<?php

namespace App\Services\NewsService;

enum NewsType: string
{
    case Transfer = 'transfer';
    case Contract = 'contract';
    case Match = 'match';
    case Job = 'job';
    case Sponsorship = 'sponsorship';
    case YouthIntake = 'youth_intake';
}
