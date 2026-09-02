<?php

namespace App\Providers;

use App\Events\MonthlyUpdate;
use App\Events\NextDay;
use App\Events\SeasonCompleted;
use App\Events\SeasonStarted;
use App\Events\Transfers\TransferEvent;
use App\Listeners\CompleteSeason;
use App\Listeners\DispatchMonthlyPlayerReindex;
use App\Listeners\News\CreateTransferNews;
use App\Listeners\NexDayTransfersSubscriber;
use App\Listeners\ProcessTransfers;
use App\Listeners\RunDailyTraining;
use App\Listeners\StartSeason;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        NextDay::class => [
            ProcessTransfers::class,
            RunDailyTraining::class,
        ],
        MonthlyUpdate::class => [
            DispatchMonthlyPlayerReindex::class,
        ],
        SeasonCompleted::class => [
            CompleteSeason::class,
        ],
        SeasonStarted::class => [
            StartSeason::class,
        ],
        TransferEvent::class => [
            CreateTransferNews::class,
        ],
    ];

    protected $subscribe = [
        NexDayTransfersSubscriber::class,
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
