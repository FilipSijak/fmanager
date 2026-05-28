<?php

namespace App\Services\NewsService\NewsTypes;

use App\Models\Transfer;
use App\Services\NewsService\NewsItem;
use App\Services\NewsService\NewsPriority;
use App\Services\TransferService\TransferTypes;

class TransferNews
{
    public function completed(Transfer $transfer): NewsItem
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


        return new NewsItem(
            instanceId: $transfer->instance_id,
            seasonId: $transfer->season_id,
            clubId: $transfer->source_club_id,
            competitionId: null,
            title: $title,
            content: $content,
            type: 'transfer',
            priority: NewsPriority::Urgent,
        );

    }

    public function medicalFailed(Transfer $transfer): NewsItem
    {
        $player = $transfer->player()->first();
        $buyingClub = $transfer->sourceClub()->first();
        $playerName = "{$player->first_name} {$player->last_name}";
        $title = "{$playerName} transfer falls through";
        $content = "{$buyingClub->name}'s move for {$playerName} has fallen through after the player failed his medical.";

        return new NewsItem(
            instanceId: $transfer->instance_id,
            seasonId: $transfer->season_id,
            clubId: $transfer->source_club_id,
            competitionId: null,
            title: $title,
            content: $content,
            type: 'transfer',
            priority: NewsPriority::Urgent,
        );
    }
}
