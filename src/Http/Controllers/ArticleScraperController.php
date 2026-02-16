<?php

namespace IstvanMolitor\ArticleScraper\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use IstvanMolitor\ArticleScraper\Services\ArticleScraperService;

class ArticleScraperController extends Controller
{
    public function __construct(
        private ArticleScraperService $scraperService
    ) {}

    public function scrape(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url'
        ]);

        try {
            $article = $this->scraperService->scrape($validated['url']);

            return response()->json([
                'success' => true,
                'data' => $article
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to scrape article: ' . $e->getMessage()
            ], 500);
        }
    }
}

