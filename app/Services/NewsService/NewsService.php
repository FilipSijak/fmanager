<?php

namespace App\Services\NewsService;

use App\Models\News;
use App\Models\Transfer;
use App\Services\TransferService\TransferTypes;

class NewsService
{
    public function publishTransferCompleted(Transfer $transfer): News
    {
        $player = $transfer->player()->first();
        $buyingClub = $transfer->sourceClub()->first();
        $sellingClub = $transfer->targetClub()->first();
        $playerName = "{$player->first_name} {$player->last_name}";
        if ($transfer->transfer_type === TransferTypes::LOAN_TRANSFER) {
            $title = "{$playerName} joins {$buyingClub->name} on loan";
            $content = "{$buyingClub->name} have completed the loan signing of {$playerName} from {$sellingClub->name}.";
        } elseif ($sellingClub) {
            $title = "{$playerName} joins {$buyingClub->name}";
            $content = "{$buyingClub->name} have completed the signing of {$playerName} from {$sellingClub->name}.";
        } else {
            $title = "{$playerName} joins {$buyingClub->name}";
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
