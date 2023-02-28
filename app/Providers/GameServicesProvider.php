<?php

namespace App\Providers;

use App\Services\CompetitionService\CompetitionService;
use App\Services\CompetitionService\ICompetitionService;
use Illuminate\Support\ServiceProvider;

class GameServicesProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(ICompetitionService::class, CompetitionService::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {

    }
}
