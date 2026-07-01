<?php

namespace IstvanMolitor\ArticleScraper\Contracts;

interface HtmlPreparerInterface
{
    public function prepare(string $html): string;
}
