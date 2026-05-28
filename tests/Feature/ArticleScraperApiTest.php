<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Molitor\ArticleParser\Article\Article;
use Molitor\ArticleParser\Services\ArticleParserService;
use Molitor\Cms\Models\PostGroup;
use Molitor\Language\Models\Language;
use Tests\TestCase;

class ArticleScraperApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_scrape_endpoint_requires_url(): void
    {
        $response = $this->postJson('/api/article-scraper/scrape', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    public function test_scrape_endpoint_returns_article_content(): void
    {
        $this->app->instance(ArticleParserService::class, new class extends ArticleParserService
        {
            public function isValidUrl(string $url): bool
            {
                return true;
            }

            public function getByUrl(string $url): ?Article
            {
                $article = new Article;
                $article->setPortal('telex.hu');
                $article->setUrl($url);
                $article->setTitle('Teszt cikk cim');
                $article->setLead('Teszt cikk lead');
                $article->setCreatedAt(null);
                $article->addAuthor('Teszt Szerzo');
                $article->getContent()->addParagraph('Ez egy teszt bekezdes.');

                return $article;
            }
        });

        $response = $this->postJson('/api/article-scraper/scrape', [
            'url' => 'https://telex.hu/belfold/2026/01/01/teszt-cikk',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('article.title', 'Teszt cikk cim')
            ->assertJsonPath('article.url', 'https://telex.hu/belfold/2026/01/01/teszt-cikk');
    }

    public function test_scrape_endpoint_returns_422_for_unsupported_url(): void
    {
        $response = $this->postJson('/api/article-scraper/scrape', [
            'url' => 'https://example.com/cikk',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_scrape_and_save_endpoint_creates_post_with_source_link_post_meta(): void
    {
        Language::query()->create([
            'code' => 'hu',
            'enabled' => true,
        ]);

        $this->app->instance(ArticleParserService::class, new class extends ArticleParserService
        {
            public function isValidUrl(string $url): bool
            {
                return true;
            }

            public function getByUrl(string $url): ?Article
            {
                $article = new Article;
                $article->setPortal('telex.hu');
                $article->setUrl($url);
                $article->setTitle('Mentendo cikk');
                $article->setLead('Mentendo lead');
                $article->setCreatedAt(null);

                return $article;
            }
        });

        $url = 'https://telex.hu/belfold/2026/01/01/mentendo-cikk';

        $response = $this->postJson('/api/article-scraper/scrape-and-save', [
            'url' => $url,
            'publish' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Mentendo cikk');

        $postId = (int) $response->json('data.post_id');

        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'title' => 'Mentendo cikk',
            'slug' => 'mentendo-cikk',
            'layout' => 'container',
        ]);

        $this->assertDatabaseHas('post_meta', [
            'post_id' => $postId,
            'name' => 'source_link',
            'meta_data' => $url,
        ]);
    }

    public function test_scrape_and_save_endpoint_automatically_attaches_group_based_on_domain(): void
    {
        Language::query()->create([
            'code' => 'hu',
            'enabled' => true,
        ]);

        $this->app->instance(ArticleParserService::class, new class extends ArticleParserService
        {
            public function isValidUrl(string $url): bool
            {
                return true;
            }

            public function getByUrl(string $url): ?Article
            {
                $article = new Article;
                $article->setPortal('telex.hu');
                $article->setUrl($url);
                $article->setTitle('Domain alapu csoport');
                $article->setLead('Lead');

                return $article;
            }
        });

        $url = 'https://telex.hu/belfold/2026/01/01/teszt';

        $response = $this->postJson('/api/article-scraper/scrape-and-save', [
            'url' => $url,
            'publish' => false,
        ]);

        $response->assertOk();
        $postId = (int) $response->json('data.post_id');

        $this->assertDatabaseHas('post_groups', [
            'slug' => 'telexhu',
            'name' => 'Telex.hu',
        ]);

        $group = PostGroup::where('slug', 'telexhu')->first();

        $this->assertDatabaseHas('post_post_groups', [
            'post_id' => $postId,
            'post_group_id' => $group->id,
        ]);

        $this->assertDatabaseHas('posts', [
            'id' => $postId,
            'layout' => 'container',
        ]);
    }
}

