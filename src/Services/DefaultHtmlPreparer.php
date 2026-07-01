<?php

namespace IstvanMolitor\ArticleScraper\Services;

use IstvanMolitor\ArticleScraper\Contracts\HtmlPreparerInterface;

class DefaultHtmlPreparer implements HtmlPreparerInterface
{
    public function prepare(string $html): string
    {
        return mb_convert_encoding($html, 'UTF-8', 'UTF-8');
    }
}
