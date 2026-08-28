<?php

namespace App\Providers;

use App\Support\GameContext;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->scoped(GameContext::class, fn () => new GameContext);
        $this->app->singleton(Client::class, fn (): Client => ClientBuilder::create()
            ->setHosts([config('elasticsearch.host')])
            ->build());
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
