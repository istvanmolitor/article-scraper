# Article Scraper Package

Laravel csomag a hírportálok cikkeinek szkrapeléséhez.

## Funkciók

- Cikk URL alapján letölti a HTML tartalmat
- Kiszedi a `<title>` tag tartalmát
- API végpont a frontendhez

## Telepítés

A csomag automatikusan települ a `composer.json` repository beállítások miatt.

## API végpont

### POST /api/article-scraper/scrape

Egy cikk URL alapján letölti és elemzi a tartalmat.

**Request:**
```json
{
  "url": "https://example.com/cikk"
}
```

**Response (siker):**
```json
{
  "success": true,
  "data": {
    "title": "A cikk címe",
    "url": "https://example.com/cikk"
  }
}
```

**Response (hiba):**
```json
{
  "success": false,
  "message": "Failed to scrape article: ..."
}
```

## Használat frontendből

A Vue komponens használata:

```vue
<script setup>
import { ArticleScraper } from '@/packages/vue-article-scraper'
</script>

<template>
  <ArticleScraper />
</template>
```

## Fejlesztés

A `ArticleScraperService` osztály tartalmazza a szkraper logikát. Jelenleg csak a title tag-et dolgozza fel, de bővíthető további elemekkel (leírás, szerző, dátum, stb.).

