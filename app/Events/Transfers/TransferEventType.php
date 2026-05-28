<?php

namespace App\Events\Transfers;

enum TransferEventType: string
{
    case Completed = 'completed';
    case MedicalFailed = 'medical_failed';
    case DelayedUntilWindow = 'delayed_until_window';
    case AffordabilityFailed = 'affordability_failed';
    case SellingClubAccepted = 'selling_club_accepted';
    case SellingClubCountered = 'selling_club_countered';
    case SellingClubDeclined = 'selling_club_declined';
    case CounterofferAccepted = 'counteroffer_accepted';
    case CounterofferRejected = 'counteroffer_rejected';
    case PlayerAccepted = 'player_accepted';
    case PlayerCountered = 'player_countered';
    case PlayerCounterofferAccepted = 'player_counteroffer_accepted';
    case PlayerCounterofferRejected = 'player_counteroffer_rejected';
    case PlayerDeclined = 'player_declined';
    case TargetClubDeclined = 'target_club_declined';
}
