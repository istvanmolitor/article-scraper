<?php

namespace IstvanMolitor\ArticleScraper\Services;

class ArticleScraperService
{
    public function scrape(string $url): array
    {
        // Fetch the HTML content
        $html = $this->fetchContent($url);

        // Parse the title tag
        $title = $this->extractTitle($html);

        return [
            'title' => $title,
            'url' => $url
        ];
    }

    private function fetchContent(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);

        $content = @file_get_contents($url, false, $context);

        if ($content === false) {
            throw new \Exception('Failed to fetch URL content');
        }

        return $content;
    }

    private function extractTitle(string $html): ?string
    {
        // Extract content from <title> tag
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }
}

