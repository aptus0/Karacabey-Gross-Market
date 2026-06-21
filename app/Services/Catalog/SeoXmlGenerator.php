<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\File;

class SeoXmlGenerator
{
    /**
     * @return array<string, mixed>
     */
    public function generate(Tenant $tenant): array
    {
        $directory = public_path('seo');
        File::ensureDirectoryExists($directory, 0755, true);

        $products = Product::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with(['categories' => function ($query) use ($tenant): void {
                $query->where('categories.tenant_id', $tenant->id)
                    ->select('categories.id', 'categories.name', 'categories.slug');
            }])
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'slug',
                'brand',
                'barcode',
                'sku',
                'description',
                'price_cents',
                'stock_quantity',
                'image_url',
                'cdn_image_url',
                'seo',
                'updated_at',
            ]);

        $sitemap = $this->buildProductSitemap($products);
        $metadata = $this->buildProductMetadata($products);

        $sitemapPath = $directory.'/product-sitemap.xml';
        $metadataPath = $directory.'/product-metadata.xml';

        File::put($sitemapPath, $sitemap);
        File::put($metadataPath, $metadata);

        return [
            'products' => $products->count(),
            'sitemap_path' => $sitemapPath,
            'metadata_path' => $metadataPath,
            'sitemap_url' => $this->storefrontUrl('/seo/product-sitemap.xml'),
            'metadata_url' => $this->storefrontUrl('/seo/product-metadata.xml'),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function buildProductSitemap($products): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $urlset = $dom->createElement('urlset');
        $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $urlset->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:image', 'http://www.google.com/schemas/sitemap-image/1.1');
        $dom->appendChild($urlset);

        foreach ($products as $product) {
            $url = $dom->createElement('url');
            $this->appendText($dom, $url, 'loc', $this->storefrontUrl('/product/'.$product->slug));
            $this->appendText($dom, $url, 'lastmod', optional($product->updated_at)->toAtomString() ?: now()->toAtomString());
            $this->appendText($dom, $url, 'changefreq', $product->stock_quantity > 0 ? 'daily' : 'weekly');
            $this->appendText($dom, $url, 'priority', $product->stock_quantity > 0 ? '0.82' : '0.58');

            $imageUrl = $this->imageUrl($product);
            if ($imageUrl) {
                $image = $dom->createElement('image:image');
                $this->appendText($dom, $image, 'image:loc', $imageUrl);
                $imageTitle = $this->seoString($product, 'image_title') ?: $product->name;
                $imageCaption = $this->seoString($product, 'image_alt') ?: $this->seoString($product, 'og_image_alt') ?: $product->name;
                $image->appendChild($dom->createElement('image:title'))->appendChild($dom->createCDATASection($imageTitle));
                $image->appendChild($dom->createElement('image:caption'))->appendChild($dom->createCDATASection($imageCaption));
                $url->appendChild($image);
            }

            $urlset->appendChild($url);
        }

        return $dom->saveXML() ?: '';
    }

    private function appendText(\DOMDocument $dom, \DOMElement $parent, string $name, string $value): void
    {
        $node = $dom->createElement($name);
        $node->appendChild($dom->createTextNode($value));
        $parent->appendChild($node);
    }

    private function buildProductMetadata($products): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('products');
        $root->setAttribute('generated_at', now()->toIso8601String());
        $root->setAttribute('site', 'Karacabey Gross Market');
        $dom->appendChild($root);

        foreach ($products as $product) {
            $item = $dom->createElement('product');
            $item->setAttribute('id', (string) $product->id);
            $item->setAttribute('active', 'true');
            $item->setAttribute('stock', (string) $product->stock_quantity);

            $category = $product->categories->first();
            $keywords = $product->seo['keywords'] ?? [];
            if (! is_array($keywords)) {
                $keywords = [];
            }

            $this->appendCData($dom, $item, 'name', $product->name);
            $this->appendCData($dom, $item, 'url', $this->storefrontUrl('/product/'.$product->slug));
            $this->appendCData($dom, $item, 'brand', $product->brand ?: 'Karacabey Gross Market');
            $this->appendCData($dom, $item, 'category', $category?->name ?? '');
            $this->appendCData($dom, $item, 'barcode', $product->barcode ?? '');
            $this->appendCData($dom, $item, 'sku', $product->sku ?? '');
            $this->appendCData($dom, $item, 'seo_title', $this->seoString($product, 'title') ?: $product->name);
            $this->appendCData($dom, $item, 'seo_description', $this->seoString($product, 'description') ?: ($product->description ?? ''));
            $this->appendCData($dom, $item, 'description', $this->seoString($product, 'content_description') ?: ($product->description ?? ''));
            $this->appendCData($dom, $item, 'image', $this->imageUrl($product) ?? '');
            $this->appendCData($dom, $item, 'image_alt', $this->seoString($product, 'image_alt') ?: $this->seoString($product, 'og_image_alt') ?: $product->name);
            $this->appendCData($dom, $item, 'keywords', implode(', ', array_slice($keywords, 0, 30)));

            $root->appendChild($item);
        }

        return $dom->saveXML() ?: '';
    }

    private function appendCData(\DOMDocument $dom, \DOMElement $parent, string $name, string $value): void
    {
        $node = $dom->createElement($name);
        $node->appendChild($dom->createCDATASection($value));
        $parent->appendChild($node);
    }

    private function seoString(Product $product, string $key): ?string
    {
        $value = is_array($product->seo) ? ($product->seo[$key] ?? null) : null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function imageUrl(Product $product): ?string
    {
        $candidate = $product->cdn_image_url ?: $product->image_url ?: $this->seoString($product, 'og_image') ?: $this->seoString($product, 'twitter_image');
        if (! $candidate) {
            return null;
        }

        if (str_starts_with($candidate, 'http://') || str_starts_with($candidate, 'https://')) {
            return $candidate;
        }

        $cdn = rtrim((string) config('app.cdn_url', ''), '/');
        if ($cdn !== '') {
            return $cdn.'/'.ltrim($candidate, '/');
        }

        return $this->storefrontUrl('/'.ltrim($candidate, '/'));
    }

    private function storefrontUrl(string $path): string
    {
        $base = rtrim((string) config('commerce.domains.storefront', 'https://karacabeygrossmarket.com'), '/');

        return $base.'/'.ltrim($path, '/');
    }
}
