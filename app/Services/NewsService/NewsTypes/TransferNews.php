<?php

namespace App\Services\NewsService\NewsTypes;

use App\Models\Transfer;
use App\Services\NewsService\NewsItem;
use App\Services\NewsService\NewsPriority;
use App\Services\NewsService\NewsType;
use App\Services\TransferService\TransferTypes;

class TransferNews
{
    public function completed(Transfer $transfer): NewsItem
    {
        $player = $transfer->player()->first();
        $buyingClub = $transfer->sourceClub()->first();
        $sellingClub = $transfer->targetClub()->first();
        $playerName = $this->playerName($transfer);

        if ($transfer->transfer_type === TransferTypes::LOAN_TRANSFER) {
            return $this->item(
                $transfer,
                "{$playerName} joins {$buyingClub->name} on loan",
                "{$buyingClub->name} have completed the loan signing of {$playerName} from {$sellingClub->name}.",
                NewsPriority::Urgent,
            );
        }

        if ($sellingClub) {
            return $this->item(
                $transfer,
                "{$playerName} joins {$buyingClub->name}",
                "{$buyingClub->name} have completed the signing of {$playerName} from {$sellingClub->name}.",
                NewsPriority::Urgent,
            );
        }

        return $this->item(
            $transfer,
            "{$playerName} joins {$buyingClub->name}",
            "{$buyingClub->name} have completed the signing of {$playerName} on a free transfer.",
            NewsPriority::Urgent,
        );
    }

    public function medicalFailed(Transfer $transfer): NewsItem
    {
        $playerName = $this->playerName($transfer);
        $buyingClub = $transfer->sourceClub()->first();

        return $this->item(
            $transfer,
            "{$playerName} transfer falls through",
            "{$buyingClub->name}'s move for {$playerName} has fallen through after the player failed his medical.",
            NewsPriority::Urgent,
        );
    }

    public function delayedUntilWindow(Transfer $transfer): NewsItem
    {
        $playerName = $this->playerName($transfer);
        $buyingClub = $transfer->sourceClub()->first();

        return $this->item(
            $transfer,
            "{$playerName} transfer delayed",
            "{$playerName}'s move to {$buyingClub->name} will be completed when the transfer window opens.",
            NewsPriority::High,
        );
    }

    public function affordabilityFailed(Transfer $transfer): NewsItem
    {
        $playerName = $this->playerName($transfer);
        $buyingClub = $transfer->sourceClub()->first();

        return $this->item(
            $transfer,
            "{$playerName} transfer cancelled",
            "{$buyingClub->name} could not complete the move for {$playerName} because the deal no longer fits the transfer budget.",
            NewsPriority::Urgent,
        );
    }

    public function sellingClubAccepted(Transfer $transfer): NewsItem
    {
        $playerName = $this->playerName($transfer);
        $buyingClub = $transfer->sourceClub()->first();
        $sellingClub = $transfer->targetClub()->first();

        return $this->item(
            $transfer,
            "Offer accepted for {$playerName}",
            "{$sellingClub->name} have accepted {$buyingClub->name}'s offer for {$playerName}.",
            NewsPriority::High,
        );
    }

    public function sellingClubCountered(Transfer $transfer): NewsItem
    {
        $playerName = $this->playerName($transfer);
        $sellingClub = $transfer->targetClub()->first();

        return $this->item(
            $transfer,
            "Counteroffer received for {$playerName}",
            "{$sellingClub->name} want improved terms before allowing {$playerName} to leave.",
            NewsPriority::High,
        );
    }

    public function sellingClubDeclined(Transfer $transfer): NewsItem
    {
        $playerName = $this->playerName($transfer);
        $buyingClub = $transfer->sourceClub()->first();
        $sellingClub = $transfer->targetClub()->first();

        return $this->item(
            $transfer,
            "{$playerName} transfer rejected",
            "{$sellingClub->name} have rejected {$buyingClub->name}'s approach for {$playerName}.",
            NewsPriority::High,
        );
    }

    public function counterofferAccepted(Transfer $transfer): NewsItem
    {
        $playerName = $this->playerName($transfer);
        $buyingClub = $transfer->sourceClub()->first();
        $sellingClub = $transfer->targetClub()->first();

        return $this->item(
            $transfer,
            "Counteroffer accepted for {$playerName}",
            "{$buyingClub->name} have accepted {$sellingClub->name}'s counteroffer for {$playerName}.",
            NewsPriority::High,
        );
    }

    public function counterofferRejected(Transfer $transfer): NewsItem
    {
        $playerName = $this->playerName($transfer);
        $buyingClub = $transfer->sourceClub()->first();
        $sellingClub = $transfer->targetClub()->first();

        return $this->item(
            $transfer,
            "{$playerName} talks break down",
            "{$buyingClub->name} could not agree terms with {$sellingClub->name} for {$playerName}.",
            NewsPriority::High,
        );
    }

    public function playerAccepted(Transfer $transfer): NewsItem
    {
        $playerName = $this->playerName($transfer);
        $buyingClub = $transfer->sourceClub()->first();

        return $this->item(
            $transfer,
            "{$playerName} agrees terms",
            "{$playerName} has agreed personal terms with {$buyingClub->name}.",
            NewsPriority::High,
        );
    }

    public function playerCountered(Transfer $transfer): NewsItem
    {
        $playerName = $this->playerName($transfer);
        $buyingClub = $transfer->sourceClub()->first();

        return $this->item(
            $transfer,
            "{$playerName} requests improved terms",
            "{$playerName} has asked {$buyingClub->name} for improved contract terms before agreeing to the move.",
            NewsPriority::High,
        );
    }

    public function playerCounterofferAccepted(Transfer $transfer): NewsItem
    {
        $playerName = $this->playerName($transfer);
        $buyingClub = $transfer->sourceClub()->first();

        return $this->item(
            $transfer,
            "{$playerName} counteroffer accepted",
            "{$buyingClub->name} have accepted {$playerName}'s contract demands.",
            NewsPriority::High,
        );
    }

    public function playerCounterofferRejected(Transfer $transfer): NewsItem
    {
        $playerName = $this->playerName($transfer);
        $buyingClub = $transfer->sourceClub()->first();

        return $this->item(
            $transfer,
            "{$playerName} contract talks collapse",
            "{$buyingClub->name} could not agree personal terms with {$playerName}.",
            NewsPriority::Urgent,
        );
    }

    public function playerDeclined(Transfer $transfer): NewsItem
    {
        $playerName = $this->playerName($transfer);
        $buyingClub = $transfer->sourceClub()->first();

        return $this->item(
            $transfer,
            "{$playerName} rejects move",
            "{$playerName} has rejected a move to {$buyingClub->name}.",
            NewsPriority::Urgent,
        );
    }

    public function targetClubDeclined(Transfer $transfer): NewsItem
    {
        return $this->sellingClubDeclined($transfer);
    }

    private function item(Transfer $transfer, string $title, string $content, NewsPriority $priority): NewsItem
    {
        return new NewsItem(
            instanceId: $transfer->instance_id,
            seasonId: $transfer->season_id,
            clubId: $transfer->source_club_id,
            competitionId: null,
            title: $title,
            content: $content,
            type: NewsType::Transfer,
            priority: $priority,
        );
    }

    private function playerName(Transfer $transfer): string
    {
        $player = $transfer->player()->first();

        return "{$player->first_name} {$player->last_name}";
    }
}
