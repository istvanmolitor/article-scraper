<?php

namespace IstvanMolitor\ArticleScraper\Repositories;

use Illuminate\Database\Eloquent\Collection;

interface PostRepositoryInterface
{
	public function getPendingScrapePosts(int $count): Collection;
}
