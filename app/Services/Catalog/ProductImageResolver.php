<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ürün isminden / barkodundan görsel aday bulur.
 *
 * Hibrit strateji:
 *   1) Türkiye pazar yeri / market site araması    — Bing RSS / DDG + meta/json-ld
 *   2) Google Custom Search API (image)            — opsiyonel (env: GOOGLE_CSE_KEY/CX)
 *   3) DuckDuckGo/Bing image search                — genel görsel arama
 *   4) Gemini AI                                  — aday üretme + en iyi görseli seçme
 *
 * Adayların toplanmasından sonra Gemini Vision (varsa) aday başlıklarını ve
 * ürün adını karşılaştırıp güven skoru ekler; en güvenli aday üste çıkar.
 *
 * Seçilen URL indirilip storage/public/products/ altına kaydedilir, böylece
 * 3rd party host'a bağımlılık kalmaz.
 */
class ProductImageResolver
{
    /** @var int Maksimum aday sayısı */
    private const MAX_CANDIDATES = 10;

    private const MAX_RAW_CANDIDATES = 36;

    private const BATCH_MAX_CANDIDATES = 5;

    private const BATCH_MAX_RAW_CANDIDATES = 14;

    /** @var int İndirilebilen görsel boyutu (byte) */
    private const MAX_DOWNLOAD_BYTES = 6 * 1024 * 1024;

    private const MIN_AI_SCORE = 80;

    private const MIN_TEXT_MATCH_SCORE = 55;

    private const TRUSTED_PRODUCT_DOMAINS = [
        'migros.com.tr',
        'migrosone.com',
        'images.migrosone.com',
        'macrocenter.com.tr',
        'carrefoursa.com',
        'sokmarket.com.tr',
        'trendyol.com',
        'cdn.dsmcdn.com',
        'hepsiburada.com',
        'productimages.hepsiburada.net',
        'amazon.com.tr',
        'cimri.com',
        'akakce.com',
        'n11.com',
        'cdn03.ciceksepeti.com',
        'ciceksepeti.com',
        'pttavm.com',
        'epttavm.com',
        'pazarama.com',
        'idefix.com',
        'alireis.com',
        'getir.com',
        'istegelsin.com',
        'a101.com.tr',
        'bim.com.tr',
        'file.com.tr',
        'hakmar.com.tr',
        'bizimtoptan.com.tr',
        'metro-tr.com',
        'ozdilekteyim.com',
        'happycenter.com.tr',
        'onurmarket.com',
        'rossmann.com.tr',
        'gratis.com',
        'watsons.com.tr',
        'eve.com.tr',
        'suwen.com.tr',
        'teknosa.com',
        'vatanbilgisayar.com',
        'mediamarkt.com.tr',
        'koctas.com.tr',
        'bauhaus.com.tr',
        'englishhome.com',
        'madamecoco.com',
        'lcw.com',
        'defacto.com.tr',
        'koton.com',
    ];

    private const PRIORITY_SEARCH_DOMAINS = [
        'trendyol.com',
        'hepsiburada.com',
        'akakce.com',
        'cimri.com',
        'n11.com',
        'pazarama.com',
        'epttavm.com',
        'amazon.com.tr',
        'migros.com.tr',
        'carrefoursa.com',
        'a101.com.tr',
        'sokmarket.com.tr',
        'bizimtoptan.com.tr',
        'metro-tr.com',
        'gratis.com',
        'watsons.com.tr',
        'rossmann.com.tr',
    ];

    /**
     * Bir ürün için görsel adaylarını döner. Her aday:
     *   ['url' => string, 'source' => 'google'|'web_page'|'gemini', 'thumb' => ?string, 'title' => ?string]
     *
     * @return array<int, array<string, string|null>>
     */
    public function suggestCandidates(Product $product, bool $batchMode = false): array
    {
        $query = $this->buildQuery($product);
        $candidates = [];
        $maxRawCandidates = $batchMode ? self::BATCH_MAX_RAW_CANDIDATES : self::MAX_RAW_CANDIDATES;
        $maxCandidates = $batchMode ? self::BATCH_MAX_CANDIDATES : self::MAX_CANDIDATES;

        if ($query !== '') {
            $this->appendCandidates($candidates, $this->webPageImageSearch($query, $batchMode), $maxRawCandidates);

            if ($this->isGoogleEnabled()) {
                $this->appendCandidates($candidates, $this->googleImageSearch($query, $batchMode ? 5 : 10), $maxRawCandidates);
                $marketplaceQueries = $batchMode
                    ? array_slice($this->marketplaceQueries($product, $query), 0, 2)
                    : $this->marketplaceQueries($product, $query);
                foreach ($marketplaceQueries as $marketplaceQuery) {
                    $this->appendCandidates($candidates, $this->googleImageSearch($marketplaceQuery, $batchMode ? 4 : 10), $maxRawCandidates);
                    if (count($candidates) >= $maxRawCandidates) {
                        break;
                    }
                }
            }

            $this->appendCandidates($candidates, $this->duckDuckGoImageSearch($query, $maxCandidates), $maxRawCandidates);

            if ($this->isGeminiEnabled() && (!$batchMode || count($candidates) < 3)) {
                $this->appendCandidates($candidates, $this->geminiSearchCandidates($product, $query, $batchMode), $maxRawCandidates);
            }
        }

        // URL bazlı tekilleştirme
        $seen = [];
        $unique = [];
        foreach ($candidates as $cand) {
            $url = $cand['url'] ?? null;
            if (!is_string($url) || $url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $unique[] = $cand;
        }

        $unique = $this->filterReliableCandidates($product, $unique);
        $unique = $this->sortCandidatesBySourcePriority($product, $unique);
        $sliced = $this->filterDownloadableCandidates(array_slice($unique, 0, $maxCandidates), $maxCandidates, $batchMode);

        // Gemini vision varsa adayları ürün adına göre yeniden sırala (en uygun üste).
        if (!$batchMode && $this->isGeminiEnabled() && count($sliced) > 0) {
            $reranked = $this->rerankWithGemini($product, $sliced);
            if ($reranked !== null) {
                $reranked = $this->filterReliableCandidates($product, $reranked, true);

                return $this->filterDownloadableCandidates($reranked, $maxCandidates, false);
            }
        }

        return $sliced;
    }

    /**
     * @param array<int, array<string, string|null>> $target
     * @param array<int, array<string, string|null>> $incoming
     */
    private function appendCandidates(array &$target, array $incoming, int $maxRawCandidates = self::MAX_RAW_CANDIDATES): void
    {
        foreach ($incoming as $candidate) {
            $target[] = $candidate;

            if (count($target) >= $maxRawCandidates) {
                return;
            }
        }
    }

    /**
     * Verilen URL'i indirip storage/public/products/ altına yazar.
     * Başarılı olursa asset() URL'ini döner; başarısızsa null.
     */
    public function downloadAndStore(string $url, Product $product): ?string
    {
        if (!preg_match('#^https?://#i', $url)) {
            return null;
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(8)
                ->withOptions(['allow_redirects' => true])
                ->withHeaders([
                    'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                    'User-Agent' => 'Mozilla/5.0 KGM Image Resolver',
                ])
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('ProductImageResolver download failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }

        if (!$response->ok()) {
            return null;
        }

        $bytes = $response->body();
        if (strlen($bytes) === 0 || strlen($bytes) > self::MAX_DOWNLOAD_BYTES) {
            return null;
        }

        $mime = $response->header('Content-Type') ?: 'image/jpeg';
        $extension = $this->extensionFor($mime, $url);
        if ($extension === null) {
            return null;
        }

        $fileName = Str::slug($product->slug ?: $product->name) . '-' . Str::lower(Str::random(6)) . '.' . $extension;
        $path = 'products/' . $fileName;

        if (!Storage::disk('public')->put($path, $bytes)) {
            return null;
        }

        return asset('storage/' . $path) . '?v=' . now()->timestamp;
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     * @return array<int, array<string, mixed>>
     */
    private function filterDownloadableCandidates(array $candidates, int $limit, bool $batchMode): array
    {
        if ($batchMode) {
            return array_slice($candidates, 0, $limit);
        }

        $filtered = [];
        foreach ($candidates as $candidate) {
            $url = $candidate['url'] ?? null;
            if (!is_string($url) || $url === '') {
                continue;
            }

            if ($this->imageUrlResponds($url)) {
                $filtered[] = $candidate;
            } elseif (isset($candidate['thumb']) && is_string($candidate['thumb']) && $candidate['thumb'] !== $url && $this->imageUrlResponds($candidate['thumb'])) {
                $candidate['url'] = $candidate['thumb'];
                $filtered[] = $candidate;
            }

            if (count($filtered) >= $limit) {
                break;
            }
        }

        return $filtered;
    }

    private function imageUrlResponds(string $url): bool
    {
        if (!preg_match('#^https?://#i', $url) || !$this->looksLikeProductImageUrl($url)) {
            return false;
        }

        try {
            $response = Http::connectTimeout(2)
                ->timeout(4)
                ->withOptions(['allow_redirects' => true])
                ->withHeaders([
                    'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                    'User-Agent' => 'Mozilla/5.0 KGM Image Resolver',
                ])
                ->head($url);

            if ($response->successful() && $this->imageResponseLooksUsable($response, $url)) {
                return true;
            }

            if (!in_array($response->status(), [403, 405, 501], true)) {
                return false;
            }
        } catch (\Throwable) {
            // Some commerce CDNs reject HEAD; try a small ranged GET below.
        }

        try {
            $response = Http::connectTimeout(2)
                ->timeout(4)
                ->withOptions(['allow_redirects' => true])
                ->withHeaders([
                    'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                    'Range' => 'bytes=0-2047',
                    'User-Agent' => 'Mozilla/5.0 KGM Image Resolver',
                ])
                ->get($url);

            return $response->successful() && $this->imageResponseLooksUsable($response, $url);
        } catch (\Throwable) {
            return false;
        }
    }

    private function imageResponseLooksUsable($response, string $url): bool
    {
        $length = (int) ($response->header('Content-Length') ?: 0);
        if ($length > self::MAX_DOWNLOAD_BYTES) {
            return false;
        }

        $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0] ?? ''));
        if ($mime !== '' && str_starts_with($mime, 'text/')) {
            return false;
        }

        return $this->extensionFor($mime ?: 'image/jpeg', $url) !== null;
    }

    /**
     * Ürün için adayları bulur, sırayla indirilebilir ilk güvenli adayı storage'a yazar.
     *
     * @return array{ok: bool, image_url?: string, candidate?: array<string, string|null>, candidates: array<int, array<string, string|null>>, message?: string}
     */
    public function resolveAndStoreBest(Product $product, bool $batchMode = false): array
    {
        $candidates = $this->suggestCandidates($product, $batchMode);

        foreach ($candidates as $candidate) {
            $url = $candidate['url'] ?? null;
            if (!is_string($url) || $url === '') {
                continue;
            }

            if (!$this->isAcceptableFinalCandidate($product, $candidate)) {
                continue;
            }

            $stored = $this->downloadAndStore($url, $product);
            if ($stored === null && $this->canUseThumbnailFallback($candidate) && isset($candidate['thumb']) && is_string($candidate['thumb']) && $candidate['thumb'] !== $url) {
                $stored = $this->downloadAndStore($candidate['thumb'], $product);
            }

            if ($stored === null) {
                continue;
            }

            return [
                'ok' => true,
                'image_url' => $stored,
                'candidate' => $candidate,
                'candidates' => $candidates,
            ];
        }

        return [
            'ok' => false,
            'candidates' => $candidates,
            'message' => $candidates === []
                ? 'Görsel adayı bulunamadı.'
                : 'Adaylar bulundu ancak indirilebilir uygun görsel seçilemedi.',
        ];
    }

    private function buildQuery(Product $product): string
    {
        $brand = trim((string) $product->brand);
        if ($this->isGenericStoreBrand($brand)) {
            $brand = '';
        }

        $parts = array_filter([
            (string) $product->name,
            $brand,
            $this->normalizedBarcode($product),
        ]);

        return trim(implode(' ', $parts));
    }

    /**
     * @return array<int, string>
     */
    private function marketplaceQueries(Product $product, string $baseQuery): array
    {
        $label = $this->productSearchLabel($product);
        $barcode = $this->normalizedBarcode($product);
        $base = trim($label !== '' ? $label : $baseQuery);
        $queries = [];

        if ($barcode !== '') {
            $queries[] = $barcode;
            $queries[] = $barcode . ' ürün görseli';
        }

        foreach (['ürün görseli', 'ürün fotoğrafı', 'market ürün'] as $suffix) {
            $queries[] = trim($base . ' ' . $suffix);
        }

        foreach (self::PRIORITY_SEARCH_DOMAINS as $domain) {
            if ($barcode !== '') {
                $queries[] = $barcode . ' site:' . $domain;
            }
            $queries[] = $base . ' site:' . $domain;
        }

        return array_values(array_filter(array_unique($queries)));
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function googleImageSearch(string $query, int $limit = 10): array
    {
        $key = (string) config('services.google_cse.key');
        $cx = (string) config('services.google_cse.cx');

        if ($key === '' || $cx === '') {
            return [];
        }

        try {
            $referer = rtrim((string) config('services.google_cse.referer', 'https://karacabeygrossmarket.com/'), '/') . '/';

            $response = Http::connectTimeout(3)->timeout(6)->withHeaders([
                'Referer' => $referer,
            ])->get('https://www.googleapis.com/customsearch/v1', [
                'key' => $key,
                'cx' => $cx,
                'q' => $query,
                'searchType' => 'image',
                'num' => min(max($limit, 1), 10),
                'safe' => 'active',
                'imgType' => 'photo',
                'gl' => 'tr',
                'hl' => 'tr',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Google CSE failure', ['error' => $e->getMessage()]);
            return [];
        }

        if (!$response->ok()) {
            return [];
        }

        $items = $response->json('items');
        if (!is_array($items)) {
            return [];
        }

        $candidates = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = $item['link'] ?? null;
            if (!is_string($url) || $url === '') {
                continue;
            }
            $pageUrl = $item['image']['contextLink'] ?? null;
            $candidates[] = [
                'url' => $url,
                'source' => 'google',
                'thumb' => $item['image']['thumbnailLink'] ?? $url,
                'title' => $item['title'] ?? null,
                'page_url' => is_string($pageUrl) ? $pageUrl : null,
            ];
        }

        return $candidates;
    }

    /**
     * DuckDuckGo image endpoint Bing sonuçlarını JSON olarak döndürür. Public API
     * olmadığı için hata/format değişiminde sessizce boş döner; bulunan adayları
     * Gemini rerank ürün adıyla ayrıca puanlar.
     *
     * @return array<int, array<string, string|null>>
     */
    private function duckDuckGoImageSearch(string $query, int $limit = self::MAX_CANDIDATES): array
    {
        $searchQuery = $query . ' ürün görseli';

        try {
            $home = Http::connectTimeout(4)
                ->timeout(8)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 KGM Image Resolver'])
                ->get('https://duckduckgo.com/', [
                    'q' => $searchQuery,
                    'iax' => 'images',
                    'ia' => 'images',
                ]);
        } catch (\Throwable $e) {
            Log::warning('DuckDuckGo image token failed', ['error' => $e->getMessage()]);
            return [];
        }

        if (!$home->ok() || !preg_match('/vqd=([\d-]+)&/', $home->body(), $matches)) {
            return [];
        }

        try {
            $response = Http::connectTimeout(4)
                ->timeout(10)
                ->withHeaders([
                    'Accept' => 'application/json, text/javascript, */*; q=0.01',
                    'Referer' => 'https://duckduckgo.com/',
                    'User-Agent' => 'Mozilla/5.0 KGM Image Resolver',
                ])
                ->get('https://duckduckgo.com/i.js', [
                    'l' => 'tr-tr',
                    'o' => 'json',
                    'q' => $searchQuery,
                    'vqd' => $matches[1],
                    'f' => ',,,',
                    'p' => '1',
                ]);
        } catch (\Throwable $e) {
            Log::warning('DuckDuckGo image search failed', ['error' => $e->getMessage()]);
            return [];
        }

        if (!$response->ok()) {
            return [];
        }

        $items = $response->json('results');
        if (!is_array($items)) {
            return [];
        }

        $candidates = [];
        $seen = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $url = $item['image'] ?? null;
            $thumb = $item['thumbnail'] ?? null;
            if (!is_string($url) || !preg_match('#^https?://#i', $url)) {
                continue;
            }
            if (isset($seen[$url]) || !$this->looksLikeProductImageUrl($url)) {
                continue;
            }

            $seen[$url] = true;
            $candidates[] = [
                'url' => $url,
                'source' => 'duckduckgo',
                'thumb' => is_string($thumb) && preg_match('#^https?://#i', $thumb) ? $thumb : $url,
                'title' => is_string($item['title'] ?? null) ? $item['title'] : null,
                'page_url' => is_string($item['url'] ?? null) ? $item['url'] : null,
            ];

            if (count($candidates) >= $limit) {
                break;
            }
        }

        return $candidates;
    }

    /**
     * Arama sonuçlarından ürün sayfası bulur, sayfalardan meta/json-ld görsel URL'i çıkarır.
     *
     * @return array<int, array<string, string|null>>
     */
    private function webPageImageSearch(string $query, bool $batchMode = false): array
    {
        $domainLimit = $batchMode ? 1 : 10;
        $pageLimit = $batchMode ? 2 : 12;
        $candidateLimit = $batchMode ? self::BATCH_MAX_CANDIDATES : self::MAX_CANDIDATES;
        $pageQueries = array_merge(
            [$query],
            array_slice(array_map(
                fn (string $domain) => $query . ' site:' . $domain,
                self::PRIORITY_SEARCH_DOMAINS
            ), 0, $domainLimit)
        );
        $pages = [];

        foreach ($pageQueries as $pageQuery) {
            $pages = array_merge(
                $pages,
                $this->bingRssProductPages($pageQuery),
                $this->duckDuckGoProductPages($pageQuery)
            );

            $pages = array_slice(array_values(array_unique($pages)), 0, $pageLimit);
            if (count($pages) >= $pageLimit) {
                break;
            }
        }

        $candidates = [];
        $seen = [];

        foreach ($pages as $pageUrl) {
            if (!$this->isLikelyProductDetailPage($pageUrl)) {
                continue;
            }

            foreach ($this->extractProductImagesFromPage($pageUrl, $query) as $candidate) {
                $url = $candidate['url'] ?? null;
                if (!is_string($url) || isset($seen[$url])) {
                    continue;
                }

                $seen[$url] = true;
                $candidates[] = $candidate;

                if (count($candidates) >= $candidateLimit) {
                    return $candidates;
                }
            }
        }

        return $candidates;
    }

    /**
     * @return array<int, string>
     */
    private function bingRssProductPages(string $query): array
    {
        try {
            $response = Http::connectTimeout(2)
                ->timeout(4)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 KGM-Bot/1.0'])
                ->get('https://www.bing.com/search', [
                    'format' => 'rss',
                    'q' => $query . ' ürün görseli',
                    'cc' => 'tr',
                    'setlang' => 'tr',
                ]);
        } catch (\Throwable $e) {
            Log::warning('Bing RSS product page search failed', ['error' => $e->getMessage()]);
            return [];
        }

        if (!$response->ok()) {
            return [];
        }

        $xml = @simplexml_load_string($response->body());
        if (!$xml || !isset($xml->channel->item)) {
            return [];
        }

        $urls = [];
        $seen = [];
        foreach ($xml->channel->item as $item) {
            $url = trim((string) ($item->link ?? ''));
            if (!preg_match('#^https?://#i', $url) || !$this->isTrustedProductPage($url)) {
                continue;
            }

            if (isset($seen[$url])) {
                continue;
            }

            $seen[$url] = true;
            $urls[] = $url;
            if (count($urls) >= 4) {
                break;
            }
        }

        return $urls;
    }

    /**
     * @return array<int, string>
     */
    private function duckDuckGoProductPages(string $query): array
    {
        try {
            $response = Http::connectTimeout(2)
                ->timeout(4)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 KGM-Bot/1.0'])
                ->get('https://html.duckduckgo.com/html/', [
                    'q' => $query . ' ürün görseli',
                ]);
        } catch (\Throwable $e) {
            Log::warning('Product page search failed', ['error' => $e->getMessage()]);
            return [];
        }

        if (!$response->ok()) {
            return [];
        }

        preg_match_all('/uddg=([^&"\']+)/i', $response->body(), $matches);

        $urls = [];
        $seen = [];
        foreach ($matches[1] ?? [] as $encoded) {
            $url = html_entity_decode(rawurldecode($encoded), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (!preg_match('#^https?://#i', $url) || !$this->isTrustedProductPage($url)) {
                continue;
            }
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $urls[] = $url;
            if (count($urls) >= 4) {
                break;
            }
        }

        return $urls;
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function extractProductImagesFromPage(string $pageUrl, string $query): array
    {
        try {
            $response = Http::connectTimeout(2)
                ->timeout(4)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 KGM-Bot/1.0'])
                ->get($pageUrl);
        } catch (\Throwable $e) {
            return [];
        }

        if (!$response->ok()) {
            return [];
        }

        $html = $response->body();
        $pageTitle = $this->extractTitle($html) ?: parse_url($pageUrl, PHP_URL_HOST);
        $images = $this->extractMetaImages($html, $pageUrl);

        foreach ($this->extractJsonLdBlocks($html) as $jsonLd) {
            $decoded = json_decode($jsonLd, true);
            foreach ($this->jsonLdImages($decoded) as $image) {
                $images[] = $this->absoluteUrl($image, $pageUrl);
            }
        }

        preg_match_all('#https?://[^"\'\s<>]+?\.(?:jpe?g|png|webp)(?:\?[^"\'\s<>]*)?#i', $html, $matches);
        foreach ($matches[0] ?? [] as $imageUrl) {
            $images[] = html_entity_decode($imageUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $candidates = [];
        $seen = [];
        foreach ($images as $imageUrl) {
            if (!is_string($imageUrl) || $imageUrl === '' || isset($seen[$imageUrl])) {
                continue;
            }
            if (!$this->looksLikeProductImageUrl($imageUrl)) {
                continue;
            }
            if (!$this->candidateMatchesMeasure($query, (string) $pageTitle, $pageUrl)) {
                continue;
            }

            $seen[$imageUrl] = true;
            $candidates[] = [
                'url' => $imageUrl,
                'source' => 'web_page',
                'thumb' => $imageUrl,
                'title' => trim((string) $pageTitle),
                'page_url' => $pageUrl,
                'ai_score' => $this->scorePageCandidate($query, (string) $pageTitle, $pageUrl, $imageUrl),
            ];

            if (count($candidates) >= 3) {
                break;
            }
        }

        usort($candidates, fn ($a, $b) => ((int) ($b['ai_score'] ?? 0)) <=> ((int) ($a['ai_score'] ?? 0)));

        return $candidates;
    }

    private function isTrustedProductPage(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?: $host;

        foreach (self::TRUSTED_PRODUCT_DOMAINS as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, string|null>> $candidates
     * @return array<int, array<string, string|null>>
     */
    private function filterReliableCandidates(Product $product, array $candidates, bool $requireAiScore = false): array
    {
        $filtered = [];

        foreach ($candidates as $candidate) {
            if (!$this->isAcceptableFinalCandidate($product, $candidate, $requireAiScore)) {
                continue;
            }

            $filtered[] = $candidate;
        }

        return array_values($filtered);
    }

    /**
     * @param array<int, array<string, string|null>> $candidates
     * @return array<int, array<string, string|null>>
     */
    private function sortCandidatesBySourcePriority(Product $product, array $candidates): array
    {
        usort($candidates, function (array $a, array $b) use ($product): int {
            $scoreA = $this->candidateSortScore($product, $a);
            $scoreB = $this->candidateSortScore($product, $b);

            return $scoreB <=> $scoreA;
        });

        return $candidates;
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function candidateSortScore(Product $product, array $candidate): int
    {
        $haystack = trim(
            (string) ($candidate['title'] ?? '') . ' ' .
            (string) ($candidate['page_url'] ?? '') . ' ' .
            (string) ($candidate['url'] ?? '') . ' ' .
            (string) ($candidate['source'] ?? '')
        );
        $score = $this->textMatchScore($product, $haystack);

        if (is_numeric($candidate['ai_score'] ?? null)) {
            $score += (int) $candidate['ai_score'];
        }

        $barcode = $this->normalizedBarcode($product);
        if ($barcode !== '' && str_contains(preg_replace('/\D+/', '', $haystack) ?: '', $barcode)) {
            $score += 90;
        }

        $hostScore = $this->trustedDomainPriority((string) ($candidate['page_url'] ?? ''))
            ?: $this->trustedDomainPriority((string) ($candidate['url'] ?? ''))
            ?: $this->trustedDomainPriority((string) ($candidate['source'] ?? ''));
        $score += $hostScore;

        $source = (string) ($candidate['source'] ?? '');
        $score += match ($source) {
            'web_page' => 45,
            'google' => 35,
            'gemini' => 30,
            'duckduckgo' => 20,
            default => 0,
        };

        return $score;
    }

    private function trustedDomainPriority(string $value): int
    {
        if ($value === '') {
            return 0;
        }

        $host = preg_match('#^https?://#i', $value)
            ? strtolower((string) parse_url($value, PHP_URL_HOST))
            : strtolower($value);
        $host = preg_replace('/^www\./', '', $host) ?: '';

        foreach (self::PRIORITY_SEARCH_DOMAINS as $index => $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return max(80 - ($index * 3), 25);
            }
        }

        foreach (self::TRUSTED_PRODUCT_DOMAINS as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return 18;
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function isAcceptableFinalCandidate(Product $product, array $candidate, bool $requireAiScore = false): bool
    {
        $url = $candidate['url'] ?? null;
        if (!is_string($url) || !preg_match('#^https?://#i', $url)) {
            return false;
        }

        $title = (string) ($candidate['title'] ?? '');
        $pageUrl = is_string($candidate['page_url'] ?? null) ? $candidate['page_url'] : '';
        $sourceHost = is_string($candidate['source'] ?? null) ? $candidate['source'] : '';
        $haystack = trim($title . ' ' . $pageUrl . ' ' . $url . ' ' . $sourceHost);

        if ($haystack === '') {
            return false;
        }

        if (!$this->candidateMatchesMeasure($this->productSearchLabel($product), $haystack, $url)) {
            return false;
        }

        $barcode = $this->normalizedBarcode($product);
        if ($barcode !== '' && str_contains(preg_replace('/\D+/', '', $haystack) ?: '', $barcode)) {
            return true;
        }

        $textScore = $this->textMatchScore($product, $haystack);
        if (!$this->isTrustedCommerceCandidate($candidate) && $textScore < 78) {
            return false;
        }

        $aiScore = $candidate['ai_score'] ?? null;
        if ($requireAiScore || is_numeric($aiScore)) {
            if (!is_numeric($aiScore) || (int) $aiScore < self::MIN_AI_SCORE) {
                return false;
            }
        }

        return $textScore >= self::MIN_TEXT_MATCH_SCORE;
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function isTrustedCommerceCandidate(array $candidate): bool
    {
        foreach (['page_url', 'url', 'thumb', 'source'] as $key) {
            $value = $candidate[$key] ?? null;
            if (!is_string($value) || $value === '') {
                continue;
            }

            if (preg_match('#^https?://#i', $value) && $this->isTrustedProductPage($value)) {
                return true;
            }

            if (!preg_match('#^https?://#i', $value)) {
                $host = preg_replace('/^www\./', '', strtolower($value)) ?: '';
                foreach (self::TRUSTED_PRODUCT_DOMAINS as $domain) {
                    if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function canUseThumbnailFallback(array $candidate): bool
    {
        return $this->isTrustedCommerceCandidate($candidate)
            && is_numeric($candidate['ai_score'] ?? null)
            && (int) $candidate['ai_score'] >= 90;
    }

    private function isLikelyProductDetailPage(string $url): bool
    {
        $path = strtolower(rawurldecode((string) parse_url($url, PHP_URL_PATH)));
        if ($path === '' || $path === '/') {
            return false;
        }

        foreach (['-p-', '/p/', '-p_', 'fiyati', 'fiyatı', 'product', 'urun', 'ürün'] as $signal) {
            if (str_contains($path, $signal)) {
                return true;
            }
        }

        if ($this->isTrustedProductPage($url) && (str_contains($path, '.html') || preg_match('/[a-z0-9-]{18,}/', $path))) {
            return true;
        }

        return false;
    }

    private function candidateMatchesMeasure(string $query, string $title, string $pageUrl): bool
    {
        $queryNumbers = $this->packageNumbers($query);
        if ($queryNumbers === []) {
            return true;
        }

        $candidateNumbers = $this->packageNumbers($title . ' ' . $pageUrl);
        if ($candidateNumbers === []) {
            return true;
        }

        return array_intersect($queryNumbers, $candidateNumbers) !== [];
    }

    private function productSearchLabel(Product $product): string
    {
        $brand = trim((string) $product->brand);

        return trim(($this->isGenericStoreBrand($brand) ? '' : $brand) . ' ' . ((string) $product->name));
    }

    private function normalizedBarcode(Product $product): string
    {
        $barcode = preg_replace('/\D+/', '', (string) $product->barcode) ?: '';

        return strlen($barcode) >= 8 ? $barcode : '';
    }

    private function textMatchScore(Product $product, string $candidateText): int
    {
        $productTokens = $this->meaningfulProductTokens($this->productSearchLabel($product));
        if ($productTokens === []) {
            return 0;
        }

        $candidateTokens = $this->searchTokens($candidateText);
        $candidateSet = array_fill_keys($candidateTokens, true);
        $matchedWeight = 0;
        $totalWeight = 0;

        foreach ($productTokens as $token) {
            $weight = strlen($token) >= 5 ? 3 : 1;
            $totalWeight += $weight;
            if (isset($candidateSet[$token])) {
                $matchedWeight += $weight;
            }
        }

        $score = $totalWeight > 0 ? (int) floor(($matchedWeight / $totalWeight) * 100) : 0;

        $brand = trim((string) $product->brand);
        if (!$this->isGenericStoreBrand($brand)) {
            $brandTokens = $this->meaningfulProductTokens($brand);
            foreach ($brandTokens as $token) {
                if (!isset($candidateSet[$token])) {
                    return min($score, 45);
                }
            }
        }

        return $score;
    }

    /**
     * @return array<int, string>
     */
    private function meaningfulProductTokens(string $value): array
    {
        $stop = array_fill_keys([
            'urun', 'urunu', 'gorsel', 'gorseli', 'resim', 'fotograf',
            'market', 'sanal', 'online', 'fiyat', 'fiyati', 'ozellikleri',
            've', 'ile', 'icin', 'bir', 'the', 'and',
        ], true);

        return array_values(array_filter(
            $this->searchTokens($value),
            fn (string $token) => strlen($token) >= 3 && !isset($stop[$token])
        ));
    }

    /**
     * @return array<int, string>
     */
    private function packageNumbers(string $value): array
    {
        preg_match_all('/\b\d{2,5}(?:[,.]\d+)?\s*(?:g|gr|gram|kg|ml|lt|l|adet|li|lü)?\b/iu', $value, $matches);

        return array_values(array_unique(array_map(
            fn ($match) => preg_replace('/\D+/', '', $match) ?: '',
            $matches[0] ?? []
        )));
    }

    private function looksLikeProductImageUrl(string $url): bool
    {
        if (!preg_match('#^https?://#i', $url)) {
            return false;
        }

        $haystack = strtolower(rawurldecode($url));
        foreach (['logo', 'favicon', 'sprite', 'banner', 'placeholder', 'icon', 'apple-touch', 'payment', 'avatar', 'splash', 'seo', '/custom/'] as $bad) {
            if (str_contains($haystack, $bad)) {
                return false;
            }
        }

        return preg_match('#\.(?:jpe?g|png|webp)(?:\?|$)#i', parse_url($url, PHP_URL_PATH) ?: $url) === 1
            || str_contains($haystack, 'images')
            || str_contains($haystack, 'product');
    }

    /**
     * @return array<int, string>
     */
    private function jsonLdImages(mixed $decoded): array
    {
        if (!is_array($decoded)) {
            return [];
        }

        $images = [];
        $stack = array_is_list($decoded) ? $decoded : [$decoded];

        while ($stack !== []) {
            $row = array_shift($stack);
            if (!is_array($row)) {
                continue;
            }

            $image = $row['image'] ?? null;
            if (is_string($image)) {
                $images[] = $image;
            } elseif (is_array($image)) {
                foreach ($image as $item) {
                    if (is_string($item)) {
                        $images[] = $item;
                    } elseif (is_array($item) && is_string($item['url'] ?? null)) {
                        $images[] = $item['url'];
                    }
                }
            }

            foreach (['@graph', 'offers', 'itemListElement'] as $key) {
                if (isset($row[$key]) && is_array($row[$key])) {
                    $stack[] = $row[$key];
                }
            }
        }

        return $images;
    }

    private function extractTitle(string $html): ?string
    {
        if (!preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return null;
        }

        $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $title !== '' ? $title : null;
    }

    /**
     * @return array<int, string>
     */
    private function extractMetaImages(string $html, string $pageUrl): array
    {
        preg_match_all('/<meta\b[^>]*>/i', $html, $matches);

        $images = [];
        foreach ($matches[0] ?? [] as $tag) {
            $key = strtolower((string) (
                $this->tagAttribute($tag, 'property')
                ?? $this->tagAttribute($tag, 'name')
                ?? $this->tagAttribute($tag, 'itemprop')
            ));

            if (!in_array($key, ['og:image', 'og:image:secure_url', 'twitter:image', 'image'], true)) {
                continue;
            }

            $content = $this->tagAttribute($tag, 'content');
            if ($content !== null && $content !== '') {
                $images[] = $this->absoluteUrl($content, $pageUrl);
            }
        }

        return $images;
    }

    /**
     * @return array<int, string>
     */
    private function extractJsonLdBlocks(string $html): array
    {
        preg_match_all('/<script\b[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches);

        return array_values(array_filter(array_map(
            fn ($block) => trim(html_entity_decode((string) $block, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            $matches[1] ?? []
        )));
    }

    private function tagAttribute(string $tag, string $attribute): ?string
    {
        $quotedAttribute = preg_quote($attribute, '/');
        if (!preg_match('/\s' . $quotedAttribute . '\s*=\s*(["\'])(.*?)\1/is', $tag, $matches)) {
            return null;
        }

        return html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function absoluteUrl(string $url, string $baseUrl): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        $base = parse_url($baseUrl);
        if (!is_array($base) || empty($base['scheme']) || empty($base['host'])) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return $base['scheme'] . '://' . $base['host'] . $url;
        }

        $path = isset($base['path']) ? dirname($base['path']) : '';
        return $base['scheme'] . '://' . $base['host'] . rtrim($path, '/') . '/' . ltrim($url, '/');
    }

    private function scorePageCandidate(string $query, string $title, string $pageUrl, string $imageUrl): int
    {
        $queryTokens = $this->searchTokens($query);
        $haystack = implode(' ', $this->searchTokens($title . ' ' . $pageUrl . ' ' . $imageUrl));
        $score = 35;

        foreach ($queryTokens as $token) {
            if (str_contains($haystack, $token)) {
                $score += strlen($token) >= 4 ? 12 : 6;
            }
        }

        return min($score, 95);
    }

    /**
     * @return array<int, string>
     */
    private function searchTokens(string $value): array
    {
        $normalized = Str::ascii(Str::lower($value));
        preg_match_all('/[a-z0-9]{2,}/', $normalized, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    private function isGoogleEnabled(): bool
    {
        return filled(config('services.google_cse.key'))
            && filled(config('services.google_cse.cx'));
    }

    private function isGeminiEnabled(): bool
    {
        return filled(config('services.gemini.key'));
    }

    private function isGenericStoreBrand(string $brand): bool
    {
        $normalized = Str::ascii(Str::lower(trim($brand)));

        return in_array($normalized, [
            '',
            'karacabey gross market',
            'k.bey gross',
            'kbey gross',
        ], true);
    }

    /**
     * Gemini AI'i Google Search grounding ile çağırır, "{ürün} ürün fotoğrafı"
     * sorgusuna karşılık döndüğü URL'leri aday olarak işler.
     *
     * @return array<int, array<string, string|null>>
     */
    private function geminiSearchCandidates(Product $product, string $query, bool $batchMode = false): array
    {
        $key   = (string) config('services.gemini.key');
        $model = (string) (config('services.gemini.model') ?: 'gemini-2.5-flash');

        if ($key === '') {
            return [];
        }

        $barcode = $this->normalizedBarcode($product);
        $candidateCount = $batchMode ? 4 : 10;
        $marketplaces = implode(', ', array_slice(self::PRIORITY_SEARCH_DOMAINS, 0, $batchMode ? 6 : 12));
        $prompt = sprintf(
            "Ürün adı: \"%s\"\nBarkod: \"%s\"\n\n"
            . "Google Search'ü kullanarak bu ürünün net, beyaz arka planlı, tek başına çekilmiş "
            . "tipik bir e-ticaret ürün görselini bul. Aşağıdaki kurallara uy:\n"
            . "- Öncelikle Türkiye pazar yerleri ve market sitelerinde ara: %s.\n"
            . "- Sonra ürünün marka/resmi sitesi, distribütör sitesi ve genel Google Images sonuçlarını değerlendir.\n"
            . "- Barkod varsa barkodla da ara; aynı barkodu taşıyan görselleri daha güvenilir say.\n"
            . "- Sadece doğrudan görsel URL'si döndür (https ile başlamalı, .jpg/.png/.webp uzantılı olmalı).\n"
            . "- Logo, banner, kolaj veya kişi resimleri DEĞİL — sadece ürünün kendisi.\n"
            . "- En iyi {$candidateCount} sonucu aşağıdaki tek satırlı JSON dizisi formatında döndür:\n"
            . "[{\"url\":\"https://...jpg\",\"title\":\"kısa açıklama\",\"source\":\"siteadi.com\",\"page_url\":\"https://ürün-sayfası\"}]\n"
            . "JSON dışında hiçbir şey yazma.",
            $query,
            $barcode,
            $marketplaces
        );

        try {
            $response = \Illuminate\Support\Facades\Http::connectTimeout(4)
                ->timeout(20)
                ->withQueryParameters(['key' => $key])
                ->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                    [
                        'contents' => [[
                            'role' => 'user',
                            'parts' => [['text' => $prompt]],
                        ]],
                        'tools' => [[ 'google_search' => new \stdClass() ]],
                        'generationConfig' => [
                            'temperature' => 0.2,
                            'maxOutputTokens' => 2048,
                            'thinkingConfig' => [
                                'thinkingBudget' => 0,
                            ],
                        ],
                    ]
                );
        } catch (\Throwable $e) {
            Log::warning('Gemini search candidates failed', ['error' => $e->getMessage()]);
            return [];
        }

        if (!$response->ok()) {
            Log::warning('Gemini search non-ok', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);
            return [];
        }

        $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
        if ($text === '') {
            return [];
        }

        // Gemini bazen yanıtı ```json ... ``` veya öncesinde açıklamayla sarar.
        $clean = preg_replace('/^\s*```(?:json)?\s*|\s*```\s*$/m', '', $text);
        if (preg_match('/\[\s*\{.*\}\s*\]/s', (string) $clean, $matches)) {
            $clean = $matches[0];
        }

        $decoded = json_decode((string) $clean, true);
        if (!is_array($decoded)) {
            return [];
        }

        $candidates = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $url = $row['url'] ?? null;
            if (!is_string($url) || !preg_match('#^https?://#i', $url)) {
                continue;
            }
            $candidates[] = [
                'url' => $url,
                'source' => 'gemini',
                'thumb' => $url,
                'title' => is_string($row['title'] ?? null) ? $row['title'] : ($row['source'] ?? null),
                'page_url' => is_string($row['page_url'] ?? null) ? $row['page_url'] : null,
            ];
        }

        return $candidates;
    }

    /**
     * Adayları Gemini Vision ile değerlendirir; ürün ile en uyumlu olanı üste alır.
     * Hata durumunda null döner, çağıran taraf orijinal sırayı kullanır.
     *
     * @param  array<int, array<string, string|null>> $candidates
     * @return array<int, array<string, string|null>>|null
     */
    private function rerankWithGemini(Product $product, array $candidates): ?array
    {
        $key   = (string) config('services.gemini.key');
        $model = (string) (config('services.gemini.model') ?: 'gemini-2.5-flash');

        if ($key === '' || count($candidates) < 1) {
            return null;
        }

        // Aday listesini numaralı şekilde Gemini'e yolla — JSON skor listesi iste.
        $lines = [];
        foreach ($candidates as $i => $c) {
            $url = (string) ($c['url'] ?? '');
            $title = (string) ($c['title'] ?? '');
            $src = (string) ($c['source'] ?? '');
            $host = (string) parse_url($url, PHP_URL_HOST);
            $path = (string) parse_url($url, PHP_URL_PATH);
            $shortPath = Str::limit($path, 120, '...');
            $lines[] = sprintf("%d) source=%s | title=%s | host=%s | image_path=%s", $i, $src, $title, $host, $shortPath);
        }

        $brand = trim((string) $product->brand);
        $productLabel = trim(($this->isGenericStoreBrand($brand) ? '' : $brand) . ' ' . ((string) $product->name));

        $prompt = sprintf(
            "Ürün: \"%s\"\n\nAşağıda bu ürün için bulunmuş görsel aday URL'leri ve "
            . "başlıkları var. Her aday için 0-100 arası bir uyum skoru ver "
            . "(100 = mükemmel ürün görseli, 0 = alakasız). Sadece şu JSON dizisini "
            . "yanıt olarak döndür, başka hiçbir şey yazma:\n\n"
            . "[{\"index\":0,\"score\":85},{\"index\":1,\"score\":40}, ...]\n\n"
            . "Adaylar:\n%s",
            $productLabel,
            implode("\n", $lines)
        );

        try {
            $response = \Illuminate\Support\Facades\Http::connectTimeout(3)
                ->timeout(8)
                ->withQueryParameters(['key' => $key])
                ->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                    [
                        'contents' => [[
                            'role' => 'user',
                            'parts' => [['text' => $prompt]],
                        ]],
                        'generationConfig' => [
                            'temperature' => 0.0,
                            'maxOutputTokens' => 2048,
                            'responseMimeType' => 'application/json',
                            'thinkingConfig' => [
                                'thinkingBudget' => 0,
                            ],
                        ],
                    ]
                );
        } catch (\Throwable $e) {
            Log::warning('Gemini rerank failed', ['error' => $e->getMessage()]);
            return null;
        }

        if (!$response->ok()) {
            return null;
        }

        $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
        if ($text === '') {
            return null;
        }

        $clean = preg_replace('/^\s*```(?:json)?\s*|\s*```\s*$/m', '', $text);
        if (preg_match('/\[\s*\{.*\}\s*\]/s', (string) $clean, $matches)) {
            $clean = $matches[0];
        }

        $scores = json_decode((string) $clean, true);
        if (!is_array($scores)) {
            return null;
        }

        // Index → score haritası
        $scoreMap = [];
        foreach ($scores as $row) {
            if (!is_array($row)) continue;
            $idx = $row['index'] ?? null;
            $sc  = $row['score'] ?? null;
            if (is_int($idx) && is_numeric($sc)) {
                $scoreMap[$idx] = (int) $sc;
            }
        }

        if ($scoreMap === []) {
            return null;
        }

        // Adayları skora göre sırala (skor yoksa 0). AI skorunu cevaba ekle.
        $withScores = [];
        foreach ($candidates as $i => $c) {
            $c['ai_score'] = $scoreMap[$i] ?? 0;
            $c['ai_ranker'] = 'gemini';
            $withScores[] = $c;
        }

        usort($withScores, fn ($a, $b) => ((int) ($b['ai_score'] ?? 0)) <=> ((int) ($a['ai_score'] ?? 0)));

        return $withScores;
    }

    private function extensionFor(string $mime, string $url): ?string
    {
        $mime = strtolower(trim(explode(';', $mime)[0] ?? $mime));

        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (isset($map[$mime])) {
            return $map[$mime];
        }

        $urlExt = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        if (in_array($urlExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return $urlExt === 'jpeg' ? 'jpg' : $urlExt;
        }

        return null;
    }
}
