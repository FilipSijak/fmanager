<?php

namespace App\Services\NewsService;

use App\Models\News;
use App\Models\Transfer;

class NewsService
{
    public function publishTransferCompleted(Transfer $transfer): News
    {
        $player = $transfer->player()->first();
        $buyingClub = $transfer->sourceClub()->first();
        $sellingClub = $transfer->targetClub()->first();
        $playerName = "{$player->first_name} {$player->last_name}";
        $title = "{$playerName} joins {$buyingClub->name}";

        if ($sellingClub) {
            $content = "{$buyingClub->name} have completed the signing of {$playerName} from {$sellingClub->name}.";
        } else {
            $content = "{$buyingClub->name} have completed the signing of {$playerName} on a free transfer.";
        }

        return News::create([
            'instance_id' => $transfer->instance_id,
            'season_id' => $transfer->season_id,
            'club_id' => $transfer->source_club_id,
            'competition_id' => null,
            'title' => $title,
            'content' => $content,
            'type' => 'transfer',
            'priority' => 5,
            'published_at' => now(),
        ]);
    }
}
