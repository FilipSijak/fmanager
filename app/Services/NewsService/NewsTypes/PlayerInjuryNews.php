<?php

namespace App\Services\NewsService\NewsTypes;

use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerInjury;
use App\Services\NewsService\NewsItem;
use App\Services\NewsService\NewsPriority;
use App\Services\NewsService\NewsType;
use Carbon\CarbonInterface;

class PlayerInjuryNews
{
    public function report(Instance $instance, Player $player, PlayerInjury $injury, CarbonInterface $date, bool $recovered = false): NewsItem
    {
        $name = trim($player->person->first_name.' '.$player->person->last_name);

        return new NewsItem(
            instanceId: $instance->id,
            seasonId: $instance->season_id,
            clubId: $player->club_id,
            competitionId: null,
            title: $name.($recovered ? ' returns to training' : ' injured'),
            content: $recovered
                ? $name.' has recovered and is available for selection. Condition will continue to improve with recovery.'
                : $name.' suffered '.$injury->injuryType->type.'. Unavailable through '.$injury->injury_end_date.'.',
            type: NewsType::Injury,
            priority: $recovered ? NewsPriority::Normal : NewsPriority::High,
            publishedAt: $date,
        );
    }
}
