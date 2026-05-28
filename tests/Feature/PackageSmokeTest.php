<?php

namespace IstvanMolitor\ArticleScraper\Tests\Feature;

use IstvanMolitor\ArticleScraper\ArticleScraperServiceProvider;
use Tests\TestCase;

class PackageSmokeTest extends TestCase
{
    public function test_service_provider_is_loaded(): void
    {
        $this->assertTrue(class_exists(ArticleScraperServiceProvider::class));
        $this->assertTrue($this->app->providerIsLoaded(ArticleScraperServiceProvider::class));
    }
}

