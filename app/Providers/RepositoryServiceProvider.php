<?php

namespace App\Providers;

use App\Repositories\CompetitionRepository;
use App\Repositories\Interfaces\ICompetitionRepository;
use App\Repositories\Interfaces\IPlayerRepository;
use App\Repositories\PlayerRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(ICompetitionRepository::class, CompetitionRepository::class);
        $this->app->bind(IPlayerRepository::class, PlayerRepository::class);
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
