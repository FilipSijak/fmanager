<?php

namespace App;

enum FinanceEntityLoanStatus: string
{
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case DEFAULTED = 'defaulted';
    case CANCELLED = 'cancelled';
}
