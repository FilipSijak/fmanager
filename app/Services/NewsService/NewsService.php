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
            'priority' => NewsPriority::Urgent->value,
            'published_at' => now(),
        ]);
    }

    public function publishTransferMedicalFailed(Transfer $transfer): News
    {
        $player = $transfer->player()->first();
        $buyingClub = $transfer->sourceClub()->first();
        $playerName = "{$player->first_name} {$player->last_name}";
        $title = "{$playerName} transfer falls through";
        $content = "{$buyingClub->name}'s move for {$playerName} has fallen through after the player failed his medical.";

        return News::create([
            'instance_id' => $transfer->instance_id,
            'season_id' => $transfer->season_id,
            'club_id' => $transfer->source_club_id,
            'competition_id' => null,
            'title' => $title,
            'content' => $content,
            'type' => 'transfer',
            'priority' => NewsPriority::Urgent->value,
            'published_at' => now(),
        ]);
    }
}
