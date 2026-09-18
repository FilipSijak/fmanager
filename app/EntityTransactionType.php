<?php

namespace App;

enum EntityTransactionType: string
{
    case PRIZE = 'prize';
    case CONTINENTAL_MATCH_PRIZE = 'continental_match_prize';
    case CONTINENTAL_ROUND_PRIZE = 'continental_round_prize';
    case SPONSORSHIP = 'sponsorship';
    case TV_REVENUE = 'tv_revenue';
    case LOAN = 'loan';
    case LOAN_REPAYMENT = 'loan_repayment';
    case STADIUM_CONSTRUCTION = 'stadium_construction';
}
