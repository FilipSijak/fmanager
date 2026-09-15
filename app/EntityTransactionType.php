<?php

namespace App;

enum EntityTransactionType: string
{
    case PRIZE = 'prize';
    case SPONSORSHIP = 'sponsorship';
    case TV_REVENUE = 'tv_revenue';
    case LOAN = 'loan';
    case LOAN_REPAYMENT = 'loan_repayment';
}
