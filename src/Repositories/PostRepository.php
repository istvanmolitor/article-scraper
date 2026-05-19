<?php

namespace IstvanMolitor\ArticleScraper\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Molitor\Cms\Models\Post;

class PostRepository implements PostRepositoryInterface
{
	public function getPendingScrapePosts(int $count): Collection
	{
		return Post::query()
			->whereHas('postMeta', function ($query) {
				$query->where('name', 'source_link')
					->whereNotNull('meta_data')
					->where('meta_data', '!=', '');
			})
			->whereDoesntHave('postMeta', function ($query) {
				$query->where('name', 'scraped_at');
			})
			->with(['postMeta', 'content'])
			->orderBy('id')
			->limit($count)
			->get();
	}
}
