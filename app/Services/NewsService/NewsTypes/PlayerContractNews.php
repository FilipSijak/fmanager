<?php

namespace App\Services\NewsService\NewsTypes;

use App\Models\Instance;
use App\Models\Player;
use App\Services\NewsService\NewsItem;
use App\Services\NewsService\NewsPriority;
use App\Services\NewsService\NewsType;
use Carbon\CarbonImmutable;

class PlayerContractNews
{
    public function reminder(Player $player, Instance $instance): NewsItem
    {
        return $this->item($player, $instance, 'Contract renewal reminder',
            'The contract expires on '.$player->contract->contract_end.'. Open Contracts to make a manual renewal offer.');
    }

    public function expired(Player $player, Instance $instance): NewsItem
    {
        return $this->item($player, $instance, 'Contract expired', 'The contract has expired and the player is now a free agent.');
    }

    public function decision(Player $player, Instance $instance, string $message): NewsItem
    {
        return $this->item($player, $instance, 'Contract renewal response', $message);
    }

    private function item(Player $player, Instance $instance, string $title, string $message): NewsItem
    {
        $name = trim($player->person->first_name.' '.$player->person->last_name);

        return new NewsItem(instanceId: (int) $instance->id, seasonId: $instance->season_id,
            clubId: (int) $player->club_id, competitionId: null, title: $title.': '.$name,
            content: $name.': '.$message, type: NewsType::Contract, priority: NewsPriority::High,
            publishedAt: CarbonImmutable::parse($instance->instance_date));
    }
}
