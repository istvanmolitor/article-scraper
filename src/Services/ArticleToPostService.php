<?php

namespace IstvanMolitor\ArticleScraper\Services;

use Illuminate\Support\Str;
use Molitor\ArticleParser\Article\Article;
use Molitor\ArticleParser\Article\ArticleContent;
use Molitor\ArticleParser\Article\ArticleContentElement;
use Molitor\Cms\Models\Author;
use Molitor\Cms\Models\Content;
use Molitor\Cms\Models\Post;
use Molitor\Cms\Models\PostGroup;
use Molitor\Cms\Models\PostMeta;
use Molitor\Cms\Repositories\PostMetaRepositoryInterface;
use Molitor\Cms\Services\ContentHandler;
use Molitor\Language\Models\Language;
use Molitor\Theme\Services\LayoutService;

class ArticleToPostService
{
    public function __construct(
        private ContentHandler $contentHandler
    ) {}

    /**
     * Convert an Article object to a CMS Post
     */
    public function convertArticleToPost(Article $article, string $sourceLink, ?int $languageId = null, bool $publish = false, ?string $layout = null): Post
    {
        if ($languageId === null) {
            $language = Language::where('code', 'hu')->first();
            $languageId = $language?->id ?? 1;
        }

        $content = Content::create([]);

        $title = $article->getTitle();
        $lead = $article->getLead();
        $mainImageUrl = $article->getMainImage()?->getSrc();

        if (strlen($title) > 255) {
            $title = substr($title, 0, 252).'...';
        }

        if (strlen($lead) > 255) {
            $lead = substr($lead, 0, 252).'...';
        }

        if ($mainImageUrl && strlen($mainImageUrl) > 255) {
            $mainImageUrl = null;
        }

        $post = Post::create([
            'title' => $title,
            'slug' => $this->generateUniqueSlug($title),
            'lead' => $lead,
            'main_image_url' => $mainImageUrl,
            'content_id' => $content->id,
            'language_id' => $languageId,
            'is_published' => $publish,
            'layout' => $layout ?? $this->getLayout(),
        ]);

        $this->assignPostGroupFromDomain($post, $sourceLink);

        $postMetaRepository = app(PostMetaRepositoryInterface::class);
        $postMetaRepository->save($post, 'source_link', $sourceLink);
        $postMetaRepository->save($post, 'scraped_at', now()->toIso8601String());

        $this->attachAuthors($post, $article->getAuthors());

        $this->processContentElements($content, $article->getContent());

        return $post->fresh(['content.contentElements', 'authors']);
    }

    public function updatePostFromArticle(Post $post, Article $article, string $sourceLink): Post
    {
        $title = $article->getTitle();
        $lead = $article->getLead();
        $mainImageUrl = $article->getMainImage()?->getSrc();

        if (strlen($title) > 255) {
            $title = substr($title, 0, 252).'...';
        }

        if (strlen($lead) > 255) {
            $lead = substr($lead, 0, 252).'...';
        }

        if ($mainImageUrl && strlen($mainImageUrl) > 255) {
            $mainImageUrl = null;
        }

        $content = $post->content;
        if ($content === null) {
            $content = Content::create([]);
            $post->content_id = $content->id;
        }

        $post->title = $title;
        $post->lead = $lead;
        $post->main_image_url = $mainImageUrl;
        $post->layout = $this->getLayout();
        $post->save();

        $this->upsertPostMeta($post->id, 'source_link', $sourceLink);
        $this->upsertPostMeta($post->id, 'scraped_at', now()->toIso8601String());

        $this->attachAuthors($post, $article->getAuthors());
        $this->processContentElements($content, $article->getContent());

        return $post->fresh(['content.contentElements', 'authors']);
    }

    /**
     * Generate a unique slug from title
     */
    private function generateUniqueSlug(string $title): string
    {
        $baseSlug = Str::slug($title);
        $slug = $baseSlug;
        $counter = 1;

        while (Post::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function assignPostGroupFromDomain(Post $post, string $sourceLink): void
    {
        $host = parse_url($sourceLink, PHP_URL_HOST);
        if (! $host) {
            return;
        }

        $host = str_replace('www.', '', $host);
        $slug = Str::slug($host);

        $postGroup = PostGroup::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst($host), 'layout' => $this->getLayout()]
        );

        $post->postGroups()->syncWithoutDetaching([$postGroup->id]);
    }

    public function getLayout(): string
    {
        return app(LayoutService::class)->getDefault();
    }

    /**
     * Attach authors to post
     */
    private function attachAuthors(Post $post, array $authorNames): void
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

        if (! empty($authorIds)) {
            $post->authors()->sync($authorIds);
        }
    }

    /**
     * Process and create content elements from article content
     */
    private function processContentElements(Content $content, ArticleContent $articleContent): void
    {
        $elements = [];

        /** @var ArticleContentElement $element */
        foreach ($articleContent->elements as $element) {
            $elementData = $element->toArray();
            $type = $elementData['type'] ?? null;

            if (! $type) {
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

        $this->contentHandler->saveContentElements($content, $elements);
    }

    /**
     * Map article parser type to CMS content element type
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
                'code' => '<iframe src="'.($elementData['src'] ?? '').'" width="100%" height="400" style="border:0;" allowfullscreen></iframe>',
                'language' => 'html',
            ],
            default => null,
        };
    }

    private function upsertPostMeta(int $postId, string $name, ?string $value): void
    {
        PostMeta::query()->updateOrCreate(
            [
                'post_id' => $postId,
                'name' => $name,
            ],
            [
                'meta_data' => $value,
            ]
        );
    }
}
