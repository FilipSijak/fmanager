<?php

namespace App\Services\NewsService;

enum NewsType: string
{
    case Injury = 'injury';
    case Transfer = 'transfer';
    case Contract = 'contract';
    case Match = 'match';
    case Job = 'job';
    case Sponsorship = 'sponsorship';
    case YouthIntake = 'youth_intake';
    case BoardReview = 'board_review';
}
