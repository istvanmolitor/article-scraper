<?php

namespace IstvanMolitor\ArticleScraper\Services;

use Illuminate\Support\Str;
use Molitor\ArticleParser\Article\Article;
use Molitor\ArticleParser\Article\ArticleContent;
use Molitor\ArticleParser\Article\ArticleContentElement;
use Molitor\Cms\Models\Author;
use Molitor\Cms\Models\Content;
use Molitor\Cms\Models\Page;
use Molitor\Cms\Services\ContentHandler;
use Molitor\Language\Models\Language;

class ArticleToPageService
{
    public function __construct(
        private ContentHandler $contentHandler
    ) {
    }

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
        $this->processContentElements($content, $article->getContent());

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
     * @param ArticleContent $articleContent
     * @return void
     */
    private function processContentElements(Content $content, ArticleContent $articleContent): void
    {
        $elements = [];

        /** @var ArticleContentElement $element */
        foreach ($articleContent as $element) {
            $elementData = $element->toArray();
            $type = $elementData['type'] ?? null;

            if (!$type) {
                continue;
            }

            $settings = $this->convertToContentHandlerSettings($type, $elementData);

            if ($settings !== null) {
                $elements[] = [
                    'type' => $this->mapArticleTypeToCmsType($type),
                    'settings' => $settings,
                ];
            }
        }

        $this->contentHandler->sevaContentElements($content, $elements);
    }

    /**
     * Map article parser type to CMS content element type
     *
     * @param string $articleType
     * @return string
     */
    private function mapArticleTypeToCmsType(string $articleType): string
    {
        return match ($articleType) {
            'paragraph' => 'text',
            'heading' => 'heading',
            'image' => 'image',
            'quote' => 'quote',
            'list' => 'list',
            'video' => 'video',
            'iframe' => 'code',
            default => 'text',
        };
    }

    /**
     * Convert article element data to ContentHandler settings format
     *
     * @param string $type
     * @param array $elementData
     * @return array|null
     */
    private function convertToContentHandlerSettings(string $type, array $elementData): ?array
    {
        return match ($type) {
            'paragraph' => [
                'text' => $elementData['content'] ?? '',
                'align' => 'left',
            ],
            'heading' => [
                'text' => $elementData['content'] ?? '',
                'level' => 2,
            ],
            'image' => [
                'src' => $elementData['src'] ?? '',
                'alt' => $elementData['alt'] ?? '',
                'width' => null,
                'height' => null,
            ],
            'quote' => [
                'text' => $elementData['content'] ?? '',
                'author' => null,
            ],
            'list' => [
                'items' => $elementData['items'] ?? [],
                'ordered' => false,
            ],
            'video' => [
                'src' => $elementData['src'] ?? '',
                'provider' => 'custom',
            ],
            'iframe' => [
                'code' => '<iframe src="' . ($elementData['src'] ?? '') . '" width="100%" height="400" style="border:0;" allowfullscreen></iframe>',
                'language' => 'html',
            ],
            default => null,
        };
    }
}



