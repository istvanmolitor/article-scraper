<?php

namespace IstvanMolitor\ArticleScraper;

use Illuminate\Support\ServiceProvider;

class ArticleScraperServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');
    }

    public function register(): void
    {
        //
    }
}

