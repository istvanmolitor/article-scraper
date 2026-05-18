<?php

namespace IstvanMolitor\ArticleScraper\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use IstvanMolitor\ArticleScraper\Http\Requests\ScrapeArticleRequest;
use Molitor\ArticleParser\Services\ArticleParserService;

class ArticleScraperController extends Controller
{
    public function __construct(
        private ArticleParserService $articleParserService
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
}
