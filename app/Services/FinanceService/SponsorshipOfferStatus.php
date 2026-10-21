<?php

namespace App\Services\FinanceService;

enum SponsorshipOfferStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
