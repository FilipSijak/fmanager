<?php

namespace App\Providers;

use App\Repositories\GameRepository;
use App\Repositories\PlayerRepository;
use App\Repositories\TransferRepository;
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
        $this->app->bind(GameRepository::class, function () {
            $gameRepo = new GameRepository();
            $gameRepo->setSeasonId($this->app->request->header('seasonId'));
            $gameRepo->setInstanceId($this->app->request->header('instanceId'));
            return $gameRepo;
        });

        $this->app->bind(TransferSearchRepository::class, function () {
            $transferSearchRepository = new TransferSearchRepository();
            $transferSearchRepository->setSeasonId($this->app->request->header('seasonId'));
            $transferSearchRepository->setInstanceId($this->app->request->header('instanceId'));
            return $transferSearchRepository;
        });

        $this->app->bind(TransferRepository::class, function () {
            $transferRepository = new TransferRepository($this->app->make(PlayerRepository::class));
            $transferRepository->setSeasonId($this->app->request->header('seasonId'));
            $transferRepository->setInstanceId($this->app->request->header('instanceId'));
            return $transferRepository;
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

