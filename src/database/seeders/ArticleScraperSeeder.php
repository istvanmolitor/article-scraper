<?php

namespace IstvanMolitor\ArticleScraper\database\seeders;

use Illuminate\Database\Seeder;
use Molitor\User\Exceptions\PermissionException;
use Molitor\User\Services\AclManagementService;

class ArticleScraperSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        try {
            /** @var AclManagementService $aclService */
            $aclService = app(AclManagementService::class);
            $aclService->createPermission('article_scraper', 'Cikk scraper kezelése', 'admin');
        } catch (PermissionException $e) {
            $this->command->error($e->getMessage());
        }
    }
}