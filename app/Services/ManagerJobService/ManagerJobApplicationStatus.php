<?php

namespace App\Services\ManagerJobService;

enum ManagerJobApplicationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
