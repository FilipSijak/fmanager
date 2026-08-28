<?php

namespace App\Listeners;

use App\Contracts\Search\PlayerSearchIndexDispatcher;
use App\Events\MonthlyUpdate;

final class DispatchMonthlyPlayerReindex
{
    public function __construct(
        private readonly PlayerSearchIndexDispatcher $searchIndexDispatcher,
    ) {}

    public function handle(MonthlyUpdate $event): void
    {
        $this->searchIndexDispatcher->reindexInstance($event->instance->id);
    }
}
