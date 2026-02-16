<?php

use Illuminate\Support\Facades\Route;
use IstvanMolitor\ArticleScraper\Http\Controllers\ArticleScraperController;

Route::prefix('api/article-scraper')->group(function () {
    Route::post('/scrape', [ArticleScraperController::class, 'scrape']);
});

