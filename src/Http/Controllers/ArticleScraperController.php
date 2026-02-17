<?php

namespace IstvanMolitor\ArticleScraper\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use IstvanMolitor\ArticleScraper\Services\ArticleToPageService;
use Molitor\ArticleParser\Services\ArticleParserService;

class ArticleScraperController extends Controller
{
    public function __construct(
        private ArticleParserService $scraperService,
        private ArticleToPageService $articleToPageService
    ) {}

    public function scrape(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url'
        ]);


            $article = $this->scraperService->getByUrl($validated['url']);

            if (!$article) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to parse article from the provided URL'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $article->toArray()
            ]);
        try {
    } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to scrape article: ' . $e->getMessage()
            ], 500);
        }
    }

    public function scrapeAndSave(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url',
            'language_id' => 'nullable|integer|exists:languages,id',
            'publish' => 'nullable|boolean',
        ]);

        try {
            // Parse article
            $article = $this->scraperService->getByUrl($validated['url']);

            if (!$article) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to parse article from the provided URL'
                ], 400);
            }

            // Convert to page
            $page = $this->articleToPageService->convertArticleToPage(
                $article,
                $validated['language_id'] ?? null,
                $validated['publish'] ?? false
            );

            return response()->json([
                'success' => true,
                'message' => 'Article successfully saved as page',
                'data' => [
                    'page_id' => $page->id,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'is_published' => $page->is_published,
                    'url' => url('/pages/' . $page->slug),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to scrape and save article: ' . $e->getMessage()
            ], 500);
        }
    }
}

