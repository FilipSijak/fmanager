<?php

namespace App\Providers;

use App\Repositories\GameRepository;
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
        $this->app->bind(GameRepository::class, function () {
            $gameRepo = new GameRepository();
            $gameRepo->setSeasonId($this->app->request->header('seasonId'));
            $gameRepo->setInstanceId($this->app->request->header('instanceId'));
            return $gameRepo;
        });
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
