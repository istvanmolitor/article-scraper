<?php

namespace IstvanMolitor\ArticleScraper;

use Illuminate\Support\ServiceProvider;
use IstvanMolitor\ArticleScraper\Services\ArticleToPageService;
use Molitor\ArticleParser\Services\ArticleParserService;

class ArticleScraperServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');
    }

    public function register(): void
    {
        $this->app->singleton(ArticleParserService::class, function ($app) {
            return new ArticleParserService();
        });

        $this->app->singleton(ArticleToPageService::class, function ($app) {
            return $app->make(ArticleToPageService::class);
        });
    }
}

