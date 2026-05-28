<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Molitor\ArticleParser\Article\Article;
use Molitor\ArticleParser\Services\ArticleParserService;
use Molitor\Cms\Models\Content;
use Molitor\Cms\Models\Post;
use Molitor\Cms\Models\PostMeta;
use Tests\TestCase;

class ArticleScraperCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_scrapes_only_posts_without_scraped_at_meta_and_respects_limit(): void
    {
        $firstPost = $this->createPostWithSourceLink('https://telex.hu/post-1', false);
        $this->createPostWithSourceLink('https://telex.hu/post-2', true);
        $thirdPost = $this->createPostWithSourceLink('https://telex.hu/post-3', false);

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
                $article->setTitle('Scraped: '.basename($url));
                $article->setLead('Scraped lead');
                $article->setCreatedAt(null);

                return $article;
            }
        });

        $this->artisan('article-scraper:scrape-posts 1')->assertExitCode(0);

        $this->assertDatabaseHas('post_meta', [
            'post_id' => $firstPost->id,
            'name' => 'scraped_at',
        ]);

        $this->assertDatabaseMissing('post_meta', [
            'post_id' => $thirdPost->id,
            'name' => 'scraped_at',
        ]);

        $this->assertDatabaseHas('posts', [
            'id' => $firstPost->id,
            'title' => 'Scraped: post-1',
            'layout' => 'container',
        ]);
    }

    private function createPostWithSourceLink(string $sourceLink, bool $alreadyScraped): Post
    {
        $content = Content::query()->create([]);

        $post = Post::query()->create([
            'title' => 'Original title',
            'slug' => 'original-title-'.uniqid(),
            'lead' => 'Original lead',
            'main_image_url' => null,
            'content_id' => $content->id,
            'language_id' => null,
            'is_published' => false,
            'layout' => 'article',
        ]);

        PostMeta::query()->create([
            'post_id' => $post->id,
            'name' => 'source_link',
            'meta_data' => $sourceLink,
        ]);

        if ($alreadyScraped) {
            PostMeta::query()->create([
                'post_id' => $post->id,
                'name' => 'scraped_at',
                'meta_data' => now()->toIso8601String(),
            ]);
        }

        return $post;
    }
}

