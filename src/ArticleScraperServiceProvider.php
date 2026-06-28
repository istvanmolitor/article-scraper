<?php

namespace IstvanMolitor\ArticleScraper;

use Illuminate\Support\ServiceProvider;
use IstvanMolitor\ArticleScraper\Console\Commands\ScrapePostsCommand;
use IstvanMolitor\ArticleScraper\Repositories\PostRepository;
use IstvanMolitor\ArticleScraper\Repositories\PostRepositoryInterface;
use IstvanMolitor\ArticleScraper\Services\ArticleToPostService;
use Molitor\ArticleParser\Services\ArticleParserService;
use Molitor\Cms\Repositories\PostMetaRepositoryInterface;
use Molitor\Cms\Repositories\PostTypeRepositoryInterface;
use Molitor\Cms\Services\ContentHandler;
use Molitor\Tinyurl\Services\HtmlService;

class ArticleScraperServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ScrapePostsCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->bind(PostRepositoryInterface::class, PostRepository::class);

        $this->app->singleton(ArticleParserService::class, function ($app) {
            return new ArticleParserService;
        });
        $this->app->singleton(ArticleToPostService::class, function ($app) {
            return new ArticleToPostService(
                $app->make(ContentHandler::class),
                $app->make(PostTypeRepositoryInterface::class),
                $app->make(PostMetaRepositoryInterface::class),
                $app->make(HtmlService::class),
            );
        });
    }
}
