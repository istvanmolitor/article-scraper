<?php

namespace IstvanMolitor\ArticleScraper\Services;

use Illuminate\Support\Str;
use Molitor\ArticleParser\Article\Article;
use Molitor\Cms\Models\Author;
use Molitor\Cms\Models\Content;
use Molitor\Cms\Models\ContentElement;
use Molitor\Cms\Models\ContentElementType;
use Molitor\Cms\Models\Page;
use Molitor\Language\Models\Language;

class ArticleToPageService
{
    private array $contentElementTypeCache = [];

    /**
     * Convert an Article object to a CMS Page
     *
     * @param Article $article
     * @param int|null $languageId
     * @param bool $publish
     * @return Page
     */
    public function convertArticleToPage(Article $article, ?int $languageId = null, bool $publish = false): Page
    {
        // Get or default language
        if ($languageId === null) {
            $language = Language::where('code', 'hu')->first();
            $languageId = $language?->id ?? 1;
        }

        // Create Content
        $content = Content::create([]);

        // Create Page
        $title = $article->getTitle();
        $lead = $article->getLead();
        $mainImageUrl = $article->getMainImage()?->getSrc();

        // Truncate fields to match database constraints (VARCHAR 255)
        if (strlen($title) > 255) {
            $title = substr($title, 0, 252) . '...';
        }

        if (strlen($lead) > 255) {
            $lead = substr($lead, 0, 252) . '...';
        }

        if ($mainImageUrl && strlen($mainImageUrl) > 255) {
            // If image URL is too long, we'll skip it rather than truncate
            // (truncated URL would be invalid anyway)
            $mainImageUrl = null;
        }

        $page = Page::create([
            'title' => $title,
            'slug' => $this->generateUniqueSlug($title),
            'lead' => $lead,
            'main_image_url' => $mainImageUrl,
            'content_id' => $content->id,
            'language_id' => $languageId,
            'is_published' => $publish,
            'layout' => 'article',
        ]);

        // Process Authors
        $this->attachAuthors($page, $article->getAuthors());

        // Process Content Elements
        $this->processContentElements($content, $article->toArray()['content']);

        return $page->fresh(['content.contentElements', 'authors']);
    }

    /**
     * Generate a unique slug from title
     *
     * @param string $title
     * @return string
     */
    private function generateUniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title);
        $slug = $baseSlug;
        $counter = 1;

        while (Page::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Attach authors to page
     *
     * @param Page $page
     * @param array $authorNames
     * @return void
     */
    private function attachAuthors(Page $page, array $authorNames): void
    {
        $authorIds = [];

        foreach ($authorNames as $authorName) {
            if (empty($authorName)) {
                continue;
            }

            $author = Author::firstOrCreate(
                ['name' => $authorName],
                ['profile_url' => null]
            );

            $authorIds[] = $author->id;
        }

        if (!empty($authorIds)) {
            $page->authors()->sync($authorIds);
        }
    }

    /**
     * Process and create content elements from article content
     *
     * @param Content $content
     * @param array $contentElements
     * @return void
     */
    private function processContentElements(Content $content, array $contentElements): void
    {
        $sort = 0;

        foreach ($contentElements as $element) {
            $type = $element['type'] ?? null;

            if (!$type) {
                continue;
            }

            $contentElementTypeId = $this->getContentElementTypeId($type);

            if (!$contentElementTypeId) {
                continue;
            }

            $settings = $this->prepareSettings($type, $element);

            ContentElement::create([
                'content_id' => $content->id,
                'content_element_type_id' => $contentElementTypeId,
                'settings' => serialize($settings),
                'sort' => $sort,
                'is_visible' => true,
            ]);

            $sort++;
        }
    }

    /**
     * Get content element type ID by name
     *
     * @param string $typeName
     * @return int|null
     */
    private function getContentElementTypeId(string $typeName): ?int
    {
        // Map article parser types to CMS types
        $typeMap = [
            'paragraph' => 'text',
            'heading' => 'heading',
            'image' => 'image',
            'quote' => 'quote',
            'list' => 'list',
            'video' => 'video',
            'iframe' => 'code', // iframes stored as code elements
        ];

        $cmsTypeName = $typeMap[$typeName] ?? null;

        if (!$cmsTypeName) {
            return null;
        }

        // Check cache
        if (isset($this->contentElementTypeCache[$cmsTypeName])) {
            return $this->contentElementTypeCache[$cmsTypeName];
        }

        // Get from database
        $type = ContentElementType::where('name', $cmsTypeName)->first();

        if ($type) {
            $this->contentElementTypeCache[$cmsTypeName] = $type->id;
            return $type->id;
        }

        return null;
    }

    /**
     * Prepare settings array for content element
     *
     * @param string $type
     * @param array $element
     * @return array
     */
    private function prepareSettings(string $type, array $element): array
    {
        return match ($type) {
            'paragraph' => [
                'text' => $element['content'] ?? '',
                'align' => 'left',
            ],
            'heading' => [
                'text' => $element['content'] ?? '',
                'level' => 2,
            ],
            'image' => [
                'src' => $element['src'] ?? '',
                'alt' => $element['alt'] ?? '',
                'width' => null,
                'height' => null,
            ],
            'quote' => [
                'text' => $element['content'] ?? '',
                'author' => $element['author'] ?? null,
            ],
            'list' => [
                'items' => $element['items'] ?? [],
                'ordered' => false,
            ],
            'video' => [
                'src' => $element['src'] ?? '',
                'provider' => 'custom',
            ],
            'iframe' => [
                'code' => '<iframe src="' . ($element['src'] ?? '') . '" width="100%" height="400" style="border:0;" allowfullscreen></iframe>',
                'language' => 'html',
            ],
            default => [],
        };
    }
}





