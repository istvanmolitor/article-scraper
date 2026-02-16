# Article Scraper - Article to Page Conversion Service

## Áttekintés

Az `ArticleToPageService` lehetővé teszi, hogy az `ArticleParserService` által visszaadott `Article` objektumot CMS `Page`-ként mentsük el az adatbázisba.

## Szolgáltatások

### ArticleToPageService

Felelősségei:
- Article objektum konvertálása CMS Page objektummá
- Tartalom elemek (paragrafusok, képek, címsorok, stb.) feldolgozása
- Szerzők kezelése (létrehozás vagy csatolás)
- Egyedi slug generálása
- Content és ContentElement rekordok létrehozása

## API Endpoint-ok

### 1. Cikk Letöltése (Scrape)
```
POST /api/article-scraper/scrape
```

**Request:**
```json
{
  "url": "https://24.hu/example-article"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "portal": "24.hu",
    "title": "Cikk címe",
    "authors": ["Szerző Neve"],
    "mainImage": {
      "src": "https://...",
      "alt": "...",
      "author": "..."
    },
    "lead": "Bevezető szöveg",
    "keywords": ["kulcsszó1", "kulcsszó2"],
    "content": [
      {
        "type": "paragraph",
        "content": "..."
      }
    ],
    "createdAt": "2026-02-16T10:00:00Z"
  }
}
```

### 2. Cikk Letöltése és Mentése Page-ként
```
POST /api/article-scraper/scrape-and-save
```

**Request:**
```json
{
  "url": "https://24.hu/example-article",
  "language_id": 1,
  "publish": false
}
```

**Paraméterek:**
- `url` (kötelező): A cikk URL-je
- `language_id` (opcionális): Nyelv ID (alapértelmezett: magyar - 1)
- `publish` (opcionális): Publikálás azonnal (alapértelmezett: false)

**Response:**
```json
{
  "success": true,
  "message": "Article successfully saved as page",
  "data": {
    "page_id": 123,
    "title": "Cikk címe",
    "slug": "cikk-cime",
    "is_published": false,
    "url": "http://localhost/pages/cikk-cime"
  }
}
```

## Támogatott Tartalmi Elemek

Az `ArticleToPageService` az alábbi article content típusokat konvertálja CMS content element típusokká:

| Article Type | CMS Type | Leírás |
|-------------|----------|---------|
| `paragraph` | `text` | Szöveges bekezdés |
| `heading` | `heading` | Címsor/alcím |
| `image` | `image` | Kép (src, alt, author) |
| `quote` | `quote` | Idézet |
| `list` | `list` | Lista (felsorolás) |
| `video` | `video` | Videó beágyazás |
| `iframe` | `code` | Iframe beágyazás (HTML kód) |

## Használat Backend-ben

```php
use IstvanMolitor\ArticleScraper\Services\ArticleToPageService;
use Molitor\ArticleParser\Services\ArticleParserService;

// Dependency injection útján
class MyController extends Controller
{
    public function __construct(
        private ArticleParserService $articleParser,
        private ArticleToPageService $articleToPage
    ) {}
    
    public function importArticle(Request $request)
    {
        // Parse article
        $article = $this->articleParser->getByUrl($request->url);
        
        // Convert to page
        $page = $this->articleToPage->convertArticleToPage(
            article: $article,
            languageId: 1,
            publish: false
        );
        
        return response()->json([
            'page_id' => $page->id,
            'title' => $page->title,
        ]);
    }
}
```

## Használat Frontend-en

A Vue komponens (`ArticleScraper.vue`) két funkciót biztosít:

1. **Letöltés**: Előnézet a cikkről
2. **Mentés Page-ként**: Mentés az adatbázisba

```vue
<template>
  <ArticleScraper />
</template>

<script setup>
import ArticleScraper from '@article-scraper/components/ArticleScraper.vue'
</script>
```

## Funkciók

### 1. Egyedi Slug Generálás
A szolgáltatás automatikusan generál egy egyedi slug-ot a cikk címéből, elkerülve az ütközéseket.

```php
// "Cikk Címe" -> "cikk-cime"
// Ha már létezik, akkor: "cikk-cime-1", "cikk-cime-2", stb.
```

### 2. Szerzők Kezelése
A szerzők automatikusan létrejönnek, ha nem léteznek, vagy csatolódnak a page-hez, ha már léteznek.

```php
// Ha "Kovács János" még nem létezik az authors táblában:
$author = Author::create(['name' => 'Kovács János']);

// Csatolás a page-hez:
$page->authors()->attach($author->id);
```

### 3. Content Elemek Konverziója
Minden article content element megfelelő CMS content element-té alakul:

```php
// Article paragraph:
[
  'type' => 'paragraph',
  'content' => 'Szöveg...'
]

// CMS content element:
ContentElement::create([
  'content_element_type_id' => 1, // text type
  'settings' => serialize([
    'text' => 'Szöveg...',
    'align' => 'left'
  ])
]);
```

## Szolgáltatás Regisztráció

A szolgáltatás automatikusan regisztrálva van az `ArticleScraperServiceProvider`-ben:

```php
public function register(): void
{
    $this->app->singleton(ArticleToPageService::class, function ($app) {
        return new ArticleToPageService();
    });
}
```

## Támogatott Portálok

Az `ArticleParserService` (függőség) az alábbi magyar hírportálokat támogatja:
- 24.hu
- index.hu
- telex.hu
- 444.hu
- story.hu

## Hibakezelés

A szolgáltatás exception-öket dob, ha:
- Az article nem parseolható
- Az adatbázis művelet sikertelen
- Hiányzó content element type-ok

```php
try {
    $page = $articleToPage->convertArticleToPage($article);
} catch (\Exception $e) {
    // Hibakezelés
    Log::error('Failed to convert article to page: ' . $e->getMessage());
}
```

## Tesztelés

```bash
# Backend test
php artisan test --filter ArticleToPageServiceTest

# API test
curl -X POST http://localhost:8000/api/article-scraper/scrape-and-save \
  -H "Content-Type: application/json" \
  -d '{"url": "https://24.hu/example-article"}'
```

## Megjegyzések

- A mentett page-ek alapértelmezetten **nem publikáltak** (draft állapot)
- A főkép URL-je a `main_image_url` mezőbe kerül
- A layout automatikusan `article`-re állítódik
- A content elemek sorrendje megőrzött (`sort` mező)

### Mező Korlátozások

Az adatbázis sémája miatt bizonyos mezők korlátozva vannak:

- **title**: maximum 255 karakter
  - Ha hosszabb, akkor 252 karakterre vágódik + "..." hozzáadódik
- **lead**: maximum 255 karakter
  - Ha hosszabb, akkor 252 karakterre vágódik + "..." hozzáadódik
- **main_image_url**: maximum 255 karakter
  - Ha hosszabb, akkor `null` értéket kap (levágás helyett, mert érvénytelen URL lenne)

**Megjegyzés:** Ha hosszabb lead szövegre van szükség, futtasd le az alábbi migrációt:

```bash
php artisan migrate
```

Ez megváltoztatja a `lead` mező típusát `VARCHAR(255)`-ről `TEXT`-re, így korlátlan hosszúságú lehet.


