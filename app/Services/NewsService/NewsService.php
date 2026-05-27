<?php

namespace App\Services\NewsService;

use App\Models\News;
use App\Models\Transfer;

class NewsService
{
    public function publishTransferCompleted(Transfer  $transfer)
    {
        return News::create([
            'instance_id' => $transfer->instance_id,
            'season_id' => $transfer->season_id,
            'club_id' => $transfer->source_club_id,
            'competition_id' => null,
            'title' => 'Transfer completed',
            'content' => 'A transfer has been completed.',
            'type' => 'transfer',
            'priority' => 5,
            'published_at' => now(),
        ]);

    }
}
