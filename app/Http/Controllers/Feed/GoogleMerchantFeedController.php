<?php

namespace App\Http\Controllers\Feed;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Google Merchant Center / Facebook Catalog uyumlu ürün feed'i.
 *
 * RSS 2.0 + Google Shopping namespace (g:) — Merchant Center'a feed URL'i olarak verilir.
 * Cache: 1 saat.
 *
 * @see https://support.google.com/merchants/answer/7052112
 */
final class GoogleMerchantFeedController extends Controller
{
    public function __invoke(Request $request, TenantResolver $tenants): Response
    {
        $tenant = $tenants->resolve($request);

        $xml = Cache::remember("tenant:{$tenant->id}:feed:google-merchant:v1", now()->addHour(), function () use ($tenant) {
            return $this->buildFeed($tenant);
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function buildFeed($tenant): string
    {
        $products = Product::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->with('categories')
            ->orderBy('id')
            ->get();

        $siteUrl = rtrim(config('app.url'), '/');
        $cdnUrl = rtrim(config('app.cdn_url') ?: $siteUrl, '/');
        $title = $tenant->name ?? 'Karacabey Gross Market';

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $rss = $dom->createElement('rss');
        $rss->setAttribute('version', '2.0');
        $rss->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:g', 'http://base.google.com/ns/1.0');
        $dom->appendChild($rss);

        $channel = $dom->createElement('channel');
        $rss->appendChild($channel);

        $channel->appendChild($dom->createElement('title', htmlspecialchars($title, ENT_XML1)));
        $channel->appendChild($dom->createElement('link', $siteUrl));
        $channel->appendChild($dom->createElement('description', htmlspecialchars("{$title} ürün katalogu", ENT_XML1)));

        foreach ($products as $product) {
            $item = $dom->createElement('item');

            $this->appendGoogleNode($dom, $item, 'id', (string) $product->id);
            $this->appendCData($dom, $item, 'title', $product->name);
            $this->appendCData($dom, $item, 'description', strip_tags($product->description ?? $product->name));
            $this->appendCData($dom, $item, 'link', $siteUrl.'/urun/'.$product->slug);

            if ($product->image_url) {
                $imageUrl = str_starts_with($product->image_url, 'http')
                    ? $product->image_url
                    : $cdnUrl.'/'.ltrim($product->image_url, '/');
                $this->appendGoogleNode($dom, $item, 'image_link', $imageUrl);
            }

            $this->appendGoogleNode($dom, $item, 'availability', $product->stock_quantity > 0 ? 'in_stock' : 'out_of_stock');
            $this->appendGoogleNode($dom, $item, 'condition', 'new');
            $this->appendGoogleNode($dom, $item, 'price', sprintf('%s TRY', number_format($product->price_cents / 100, 2, '.', '')));

            if ($product->compare_at_price_cents && $product->compare_at_price_cents > $product->price_cents) {
                $this->appendGoogleNode($dom, $item, 'sale_price', sprintf('%s TRY', number_format($product->price_cents / 100, 2, '.', '')));
            }

            $brand = $product->brand ?: 'Karacabey Gross Market';
            $this->appendGoogleNode($dom, $item, 'brand', $brand);

            if ($product->barcode) {
                $this->appendGoogleNode($dom, $item, 'gtin', $product->barcode);
            } else {
                $this->appendGoogleNode($dom, $item, 'mpn', 'KGM-'.$product->id);
            }

            // Kategori path
            $category = $product->categories->first();
            if ($category) {
                $this->appendCData($dom, $item, 'g:product_type', $category->name);
            }

            // Google product taxonomy — opsiyonel; doldurulmazsa Google otomatik eşler
            $this->appendGoogleNode($dom, $item, 'identifier_exists', $product->barcode ? 'yes' : 'no');

            $channel->appendChild($item);
        }

        return $dom->saveXML() ?: '';
    }

    private function appendGoogleNode(\DOMDocument $dom, \DOMElement $parent, string $name, string $value): void
    {
        $node = $dom->createElement('g:'.$name);
        $node->appendChild($dom->createTextNode($value));
        $parent->appendChild($node);
    }

    private function appendCData(\DOMDocument $dom, \DOMElement $parent, string $name, string $value): void
    {
        $isGoogleNs = str_starts_with($name, 'g:');
        $node = $dom->createElement($name);
        $node->appendChild($dom->createCDATASection($value));
        $parent->appendChild($node);
        // CDATA için namespace prefix createElement ile düzgün gelmiyor olabilir; emin olmak için
        // doğrudan g:name kullandığımız yerlerde createElement'in namespace bind etmesine güveniyoruz.
        unset($isGoogleNs);
    }
}
