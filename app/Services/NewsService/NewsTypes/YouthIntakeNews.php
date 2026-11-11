<?php

namespace App\Services\NewsService\NewsTypes;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Services\NewsService\NewsItem;
use App\Services\NewsService\NewsPriority;
use App\Services\NewsService\NewsType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class YouthIntakeNews
{
    /** @param Collection<int, Player> $players */
    public function report(Instance $instance, Club $club, Collection $players): NewsItem
    {
        $date = CarbonImmutable::parse($instance->instance_date);
        $names = $players->map(function (Player $player) use ($date): string {
            $age = (int) CarbonImmutable::parse($player->person->dob)->diffInYears($date);

            return trim($player->person->first_name.' '.$player->person->last_name)." ({$age}, {$player->position})";
        })->implode("\n");

        return new NewsItem(
            instanceId: (int) $instance->id,
            seasonId: (int) $instance->season_id,
            clubId: (int) $club->id,
            competitionId: null,
            title: 'Youth intake: '.$club->name,
            content: $players->count()." academy graduates have joined your squad and signed their first contracts.\n\n".$names,
            type: NewsType::YouthIntake,
            priority: NewsPriority::High,
            publishedAt: $date,
        );
    }
}
