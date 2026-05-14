<?php

namespace App\Providers;

use App\Repositories\GameRepository;
use App\Repositories\TransferSearchRepository;
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
        $this->app->bind(GameRepository::class);
        $this->app->bind(TransferSearchRepository::class);
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
