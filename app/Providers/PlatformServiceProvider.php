<?php

namespace App\Providers;

use App\Services\CompetitionService\CompetitionService;
use App\Services\CompetitionService\ICompetitionService;
use App\Services\InstanceService\IInstanceService;
use App\Services\InstanceService\InstanceService;
use App\Services\PersonService\IPersonService;
use App\Services\PersonService\PersonService;
use Illuminate\Support\ServiceProvider;

class PlatformServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(IInstanceService::class, InstanceService::class);
        $this->app->bind(IPersonService::class, PersonService::class);
        $this->app->bind(ICompetitionService::class, CompetitionService::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
