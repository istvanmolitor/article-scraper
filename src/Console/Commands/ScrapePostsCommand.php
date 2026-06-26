<?php

namespace IstvanMolitor\ArticleScraper\Console\Commands;

use Illuminate\Console\Command;
use IstvanMolitor\ArticleScraper\Repositories\PostRepositoryInterface;
use IstvanMolitor\ArticleScraper\Services\ArticleToPostService;
use Molitor\ArticleParser\Exceptions\ArticleFetchException;
use Molitor\ArticleParser\Exceptions\InvalidArticleException;
use Molitor\ArticleParser\Services\ArticleParserService;

class ScrapePostsCommand extends Command
{
    protected $signature = 'article-scraper:scrape-posts {count : Hany postot scrape-eljen}';

    protected $description = 'Lescrape-eli a forras linkkel rendelkezo, de meg nem scrape-elt postokat.';

    public function __construct(
        private ArticleParserService $articleParserService,
        private ArticleToPostService $articleToPostService,
        private PostRepositoryInterface $postRepository
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = (int) $this->argument('count');

        if ($count <= 0) {
            $this->error('A count argumentumnak pozitiv szamnak kell lennie.');

            return self::INVALID;
        }

        $posts = $this->postRepository->getPendingScrapePosts($count);

        if ($posts->isEmpty()) {
            $this->info('Nincs olyan post, amihez hianyzik a scraped_at meta.');

            return self::SUCCESS;
        }

        $processed = 0;
        $failed = 0;

        foreach ($posts as $post) {
            $sourceLink = (string) optional(
                $post->postMeta->firstWhere('name', 'source_link')
            )->meta_data;

            if ($sourceLink === '') {
                $this->warn("Post #{$post->id}: hianyzik a source_link meta.");
                $failed++;

                continue;
            }

            if (! $this->articleParserService->isValidUrl($sourceLink)) {
                $this->warn("Post #{$post->id}: nem tamogatott URL ({$sourceLink}).");
                $failed++;

                continue;
            }

            try {
                $article = $this->articleParserService->getByUrl($sourceLink);
            } catch (ArticleFetchException $e) {
                $this->warn("Post #{$post->id}: nem sikerult letolteni ({$e->getMessage()}).");
                $failed++;

                continue;
            } catch (InvalidArticleException $e) {
                $this->warn("Post #{$post->id}: ervenytelen cikk ({$e->getMessage()}).");
                $failed++;

                continue;
            }

            $this->articleToPostService->updatePostFromArticle($post, $article, $sourceLink);

            $this->info("Post #{$post->id}: sikeresen scrape-elve.");
            $processed++;
        }

        $this->line("Feldolgozva: {$processed}, sikertelen: {$failed}.");

        return self::SUCCESS;
    }
}
