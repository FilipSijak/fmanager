<?php

namespace App\Providers;

use App\Contracts\Search\PlayerSearchIndexDispatcher;
use App\Contracts\Search\PlayerSearchIndexer;
use App\Services\SearchService\ElasticsearchPlayerSearchIndexer;
use App\Services\SearchService\LaravelPlayerSearchIndexDispatcher;
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
        $this->app->bind(PlayerSearchIndexDispatcher::class, LaravelPlayerSearchIndexDispatcher::class);
        $this->app->bind(PlayerSearchIndexer::class, ElasticsearchPlayerSearchIndexer::class);
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
