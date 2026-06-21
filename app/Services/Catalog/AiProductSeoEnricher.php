<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AiProductSeoEnricher
{
    private const SITE_NAME = 'Karacabey Gross Market';
    private const SITE_DOMAIN = 'https://karacabeygrossmarket.com';
    private const LOCALE = 'tr_TR';

    /**
     * @param  Collection<int, Product>  $products
     * @return array<int, array<string, mixed>>
     */
    public function enrichBatch(Collection $products): array
    {
        if ($products->isEmpty()) {
            return [];
        }

        $aiPayloads = $this->geminiPayloads($products);

        return $products
            ->mapWithKeys(function (Product $product) use ($aiPayloads): array {
                $payload = $aiPayloads[$product->id] ?? $this->fallbackPayload($product);

                return [$product->id => $this->buildUpdate($product, $payload)];
            })
            ->all();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<int, array<string, mixed>>
     */
    private function geminiPayloads(Collection $products): array
    {
        $key = (string) config('services.gemini.key');
        if ($key === '') {
            return [];
        }

        $model = (string) (config('services.gemini.model') ?: 'gemini-2.5-flash');

        try {
            $response = Http::timeout(60)
                ->retry(2, 750)
                ->withQueryParameters(['key' => $key])
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [[
                            'text' => $this->buildPrompt($products),
                        ]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.35,
                        'topP' => 0.8,
                        'maxOutputTokens' => 8192,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if (! $response->ok()) {
                Log::warning('Gemini product SEO non-ok', [
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 500),
                ]);

                return [];
            }

            $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
            $items = $this->decodeJson($text);

            if (! is_array($items)) {
                return [];
            }

            $payloads = [];
            foreach ($items as $item) {
                if (! is_array($item) || ! isset($item['id'])) {
                    continue;
                }

                $payloads[(int) $item['id']] = $item;
            }

            return $payloads;
        } catch (Throwable $e) {
            Log::warning('Gemini product SEO failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function buildPrompt(Collection $products): string
    {
        $items = $products->map(function (Product $product): array {
            $category = $product->categories->first();

            return [
                'id' => $product->id,
                'name' => $product->name,
                'brand' => $product->brand,
                'category' => $category?->name,
                'barcode' => $product->barcode,
                'sku' => $product->sku,
                'unit' => $product->unit_name,
                'price_try' => $product->price_cents > 0 ? round($product->price_cents / 100, 2) : null,
                'stock' => $product->stock_quantity,
                'image_url' => $this->resolveImageUrl($product->cdn_image_url ?? null, $product->image_url ?? null),
                'existing_description' => $product->description,
            ];
        })->values()->all();

        $json = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
Sen bir Türkçe e-ticaret market SEO uzmanısın. Aşağıdaki ürünler için sadece geçerli JSON array döndür.

Kurallar:
- Ürün adı, marka, kategori, barkod/SKU ve görsel URL ipuçlarını dikkate al.
- Ürün adında internet sitesi, alakasız marka veya başka mağaza adı gibi parazit varsa açıklamaya taşıma.
- Tıbbi/sağlık iddiası, sahte kampanya, olmayan menşe/gramaj/lezzet/özellik uydurma.
- Açıklama doğal Türkçe olsun, market alışverişine uygun olsun, ürün adıyla birebir alakalı olsun.
- SEO title en fazla 65 karakter, SEO description en fazla 155 karakter.
- description 180-320 karakter arası.
- keywords 8-14 kısa ve alakalı ifade olsun.
- image_alt görsel arama için ürün adı + marka/kategori odaklı doğal alt metin olsun.

JSON şeması:
[
  {
    "id": 123,
    "description": "...",
    "seo_title": "...",
    "seo_description": "...",
    "short_title": "...",
    "keywords": ["..."],
    "image_alt": "...",
    "image_title": "..."
  }
]

Ürünler:
{$json}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUpdate(Product $product, array $payload): array
    {
        $category = $product->categories->first();
        $categoryName = $category?->name ?? 'Market';
        $categorySlug = $category?->slug;
        $brand = trim((string) ($product->brand ?? ''));
        $hasBrand = $brand !== '' && strcasecmp($brand, self::SITE_NAME) !== 0;
        $imageUrl = $this->resolveImageUrl($product->cdn_image_url ?? null, $product->image_url ?? null);
        $canonical = self::SITE_DOMAIN.'/product/'.$product->slug;
        $inStock = (int) $product->stock_quantity > 0;
        $price = $product->price_cents > 0 ? round($product->price_cents / 100, 2) : null;
        $description = $this->cleanText((string) ($payload['description'] ?? ''), 320)
            ?: $this->fallbackPayload($product)['description'];
        $seoDescription = $this->cleanText((string) ($payload['seo_description'] ?? ''), 155)
            ?: Str::limit($description, 155, '');
        $title = $this->cleanText((string) ($payload['seo_title'] ?? ''), 65)
            ?: $this->fallbackPayload($product)['seo_title'];
        $shortTitle = $this->cleanText((string) ($payload['short_title'] ?? ''), 80)
            ?: ($hasBrand ? "{$product->name} - {$brand}" : $product->name);
        $imageAlt = $this->cleanText((string) ($payload['image_alt'] ?? ''), 125)
            ?: ($hasBrand ? "{$product->name} {$brand} ürün görseli" : "{$product->name} ürün görseli");
        $imageTitle = $this->cleanText((string) ($payload['image_title'] ?? ''), 125) ?: $imageAlt;
        $keywords = $this->keywords($payload, $product, $brand, $categoryName, $hasBrand);

        $schema = [
            '@type' => 'Product',
            'name' => $product->name,
            'sku' => $product->sku ?: (string) $product->id,
            'mpn' => $product->sku ?: (string) $product->id,
            'gtin13' => $product->barcode,
            'brand' => $hasBrand ? ['@type' => 'Brand', 'name' => $brand] : ['@type' => 'Brand', 'name' => self::SITE_NAME],
            'category' => $categoryName,
            'image' => $imageUrl ? [$imageUrl] : [],
            'description' => $description,
            'offers' => [
                '@type' => 'Offer',
                'url' => $canonical,
                'price' => $price,
                'priceCurrency' => 'TRY',
                'priceValidUntil' => now()->addDays(14)->toDateString(),
                'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => [
                    '@type' => 'Organization',
                    'name' => self::SITE_NAME,
                ],
            ],
        ];

        $breadcrumbs = [
            ['name' => 'Ana Sayfa', 'url' => self::SITE_DOMAIN],
            ['name' => 'Ürünler', 'url' => self::SITE_DOMAIN.'/products'],
        ];

        if ($category) {
            $breadcrumbs[] = [
                'name' => $category->name,
                'url' => self::SITE_DOMAIN.'/kategori/'.$category->slug,
            ];
        }

        $breadcrumbs[] = ['name' => $product->name, 'url' => $canonical];

        $existingSeo = is_array($product->seo) ? $product->seo : [];
        $seo = array_merge($existingSeo, [
            'title' => $title,
            'short_title' => $shortTitle,
            'description' => $seoDescription,
            'content_description' => $description,
            'keywords' => $keywords,
            'canonical' => $canonical,
            'robots' => 'index, follow, max-image-preview:large, max-snippet:-1',
            'og_title' => $title,
            'og_description' => $seoDescription,
            'og_type' => 'product',
            'og_locale' => self::LOCALE,
            'og_url' => $canonical,
            'og_site_name' => self::SITE_NAME,
            'og_image' => $imageUrl,
            'og_image_alt' => $imageAlt,
            'twitter_card' => $imageUrl ? 'summary_large_image' : 'summary',
            'twitter_title' => $title,
            'twitter_description' => $seoDescription,
            'twitter_image' => $imageUrl,
            'image_alt' => $imageAlt,
            'image_title' => $imageTitle,
            'schema' => $schema,
            'breadcrumbs' => $breadcrumbs,
            'category_name' => $categoryName,
            'category_slug' => $categorySlug,
            'brand' => $hasBrand ? $brand : null,
            'price_tl' => $price !== null ? number_format($price, 2, ',', '.') : null,
            'in_stock' => $inStock,
            'availability' => $inStock ? 'InStock' : 'OutOfStock',
            'currency' => 'TRY',
            'language' => 'tr',
            'region' => 'TR-16',
            'ai_enriched' => true,
            'ai_provider' => 'gemini',
            'ai_model' => (string) (config('services.gemini.model') ?: 'gemini-2.5-flash'),
            'ai_enriched_at' => now()->toIso8601String(),
            'modified_time' => now()->toIso8601String(),
        ]);

        $metadata = is_array($product->metadata) ? $product->metadata : [];
        $metadata['ai_seo'] = [
            'provider' => 'gemini',
            'model' => (string) (config('services.gemini.model') ?: 'gemini-2.5-flash'),
            'enriched_at' => now()->toIso8601String(),
        ];

        return [
            'description' => $description,
            'seo' => $seo,
            'metadata' => $metadata,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackPayload(Product $product): array
    {
        $category = $product->categories->first();
        $categoryName = $category?->name ?? 'Market';
        $brand = trim((string) ($product->brand ?? ''));
        $hasBrand = $brand !== '' && strcasecmp($brand, self::SITE_NAME) !== 0;
        $stockText = $product->stock_quantity > 0 ? 'stokta olan' : 'stoğa eklenmesi beklenen';
        $brandText = $hasBrand ? "{$brand} markalı " : '';
        $priceText = $product->price_cents > 0 ? ' güncel fiyatıyla' : '';

        $description = "{$product->name}, {$brandText}{$categoryName} kategorisinde {$stockText} bir üründür. Karacabey Gross Market'te ürün bilgisi, görseli ve stok durumu kontrol edilerek{$priceText} online market alışverişi için listelenir.";

        return [
            'description' => Str::limit($description, 320, ''),
            'seo_title' => Str::limit(($hasBrand ? "{$product->name} {$brand}" : $product->name).' | '.self::SITE_NAME, 65, ''),
            'seo_description' => Str::limit("{$product->name} {$categoryName} ürününü Karacabey Gross Market'te incele; stok, fiyat ve online sipariş bilgilerine ulaş.", 155, ''),
            'short_title' => $hasBrand ? "{$product->name} - {$brand}" : $product->name,
            'keywords' => [],
            'image_alt' => $hasBrand ? "{$product->name} {$brand} ürün görseli" : "{$product->name} ürün görseli",
            'image_title' => $product->name,
        ];
    }

    /**
     * @return string[]
     */
    private function keywords(array $payload, Product $product, string $brand, string $category, bool $hasBrand): array
    {
        $keywords = is_array($payload['keywords'] ?? null) ? $payload['keywords'] : [];
        $base = [
            $product->name,
            "{$product->name} fiyat",
            "{$product->name} sipariş",
            "{$product->name} online market",
            $category,
            "{$category} Karacabey",
            'Karacabey Gross Market',
            'online market',
        ];

        if ($hasBrand) {
            array_splice($base, 1, 0, [$brand, "{$brand} {$product->name}"]);
        }

        return collect(array_merge($keywords, $base))
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => Str::limit(trim(preg_replace('/\s+/', ' ', $value) ?: ''), 80, ''))
            ->unique(fn (string $value): string => Str::lower($value))
            ->take(20)
            ->values()
            ->all();
    }

    private function cleanText(string $value, int $limit): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        $value = preg_replace('/https?:\/\/\S+|www\.\S+/i', '', $value) ?: '';

        return trim(Str::limit($value, $limit, ''));
    }

    private function resolveImageUrl(?string $cdn, ?string $image): ?string
    {
        $candidate = $cdn ?: $image;
        if (! $candidate) {
            return null;
        }

        if (str_starts_with($candidate, 'http://') || str_starts_with($candidate, 'https://')) {
            return $candidate;
        }

        return self::SITE_DOMAIN.'/'.ltrim($candidate, '/');
    }

    private function decodeJson(string $text): mixed
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text) ?: $text;

        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        if (preg_match('/(\[.*\]|\{.*\})/s', $text, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return null;
    }
}
