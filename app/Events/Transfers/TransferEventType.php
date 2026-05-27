<?php

namespace App\Events\Transfers;

enum TransferEventType: string
{
    case Completed = 'completed';
    case MedicalFailed = 'medical_failed';
}
