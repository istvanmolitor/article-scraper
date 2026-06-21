<?php

namespace IstvanMolitor\ArticleScraper\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use IstvanMolitor\ArticleScraper\Http\Requests\ScrapeAndSaveArticleRequest;
use IstvanMolitor\ArticleScraper\Http\Requests\ScrapeArticleRequest;
use IstvanMolitor\ArticleScraper\Services\ArticleToPostService;
use Molitor\ArticleParser\Services\ArticleParserService;
use Molitor\Language\Repositories\LanguageRepositoryInterface;
use Molitor\Theme\Services\LayoutService;

class ArticleScraperController extends Controller
{
    public function __construct(
        private ArticleParserService $articleParserService,
        private ArticleToPostService $articleToPostService,
        private LanguageRepositoryInterface $languageRepository,
        private LayoutService $layoutService
    ) {}

    public function scrape(ScrapeArticleRequest $request): JsonResponse
    {
        $url = (string) $request->string('url');
        if (! $this->articleParserService->isValidUrl($url)) {
            return response()->json([
                'success' => false,
                'message' => 'A megadott URL jelenleg nem tamogatott.',
            ], 422);
        }
        $article = $this->articleParserService->getByUrl($url);
        if ($article === null) {
            return response()->json([
                'success' => false,
                'message' => 'A cikk tartalma nem olvashato be a megadott URL-rol.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'article' => $article->toArray(),
        ]);
    }

    public function scrapeAndSave(ScrapeAndSaveArticleRequest $request): JsonResponse
    {
        $url = (string) $request->string('url');
        if (! $this->articleParserService->isValidUrl($url)) {
            return response()->json([
                'success' => false,
                'message' => 'A megadott URL jelenleg nem tamogatott.',
            ], 422);
        }
        $article = $this->articleParserService->getByUrl($url);
        if ($article === null) {
            return response()->json([
                'success' => false,
                'message' => 'A cikk tartalma nem olvashato be a megadott URL-rol.',
            ], 422);
        }
        $layout = $request->string('layout')->value() ?: null;
        if ($layout && ! $this->layoutService->isValidLayout($layout)) {
            $layout = null;
        }

        $post = $this->articleToPostService->convertArticleToPost(
            article: $article,
            sourceLink: $url,
            languageId: $this->languageRepository->getIdByCode($article->getLanguage() ?? 'hu'),
            publish: (bool) $request->boolean('publish', false),
            layout: $layout,
        );

        return response()->json([
            'success' => true,
            'message' => 'A cikk sikeresen elmentve postkent.',
            'data' => [
                'post_id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'is_published' => $post->is_published,
            ],
        ]);
    }
}
