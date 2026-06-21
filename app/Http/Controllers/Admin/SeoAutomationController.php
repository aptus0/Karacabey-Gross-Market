<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\Catalog\SeoXmlGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class SeoAutomationController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $this->tenant();

        return view('admin.seo-automation.index', [
            'stats' => $this->stats($tenant),
            'xml' => $this->xmlStatus(),
            'aiJob' => $this->aiJobStatus(),
            'logLines' => $this->tailLog(),
        ]);
    }

    public function generateXml(SeoXmlGenerator $generator): RedirectResponse
    {
        $result = $generator->generate($this->tenant());
        Cache::forget('tenant:1:feed:google-merchant:v1');

        return back()->with('status', "{$result['products']} ürün için SEO XML oluşturuldu.");
    }

    public function startAi(Request $request): RedirectResponse
    {
        $limit = $request->integer('limit') ?: null;
        $chunk = max(5, min(25, $request->integer('chunk') ?: 25));
        $force = $request->boolean('force');

        if ($this->aiJobStatus()['running']) {
            return back()->with('status', 'AI SEO işi zaten çalışıyor; logtan ilerlemeyi takip edebilirsin.');
        }

        $command = 'php artisan kgm:ai-product-seo --chunk='.escapeshellarg((string) $chunk);
        if ($limit !== null) {
            $command .= ' --limit='.escapeshellarg((string) max(1, $limit));
        }
        if ($force) {
            $command .= ' --force';
        }

        $log = storage_path('logs/ai-product-seo.log');
        $shell = 'cd '.escapeshellarg(base_path()).' && ('.$command.' >> '.escapeshellarg($log).' 2>&1 & echo $!)';
        $pid = trim((string) shell_exec($shell));

        return back()->with('status', 'AI SEO arka planda başlatıldı'.($pid !== '' ? " (PID {$pid})." : '.'));
    }

    public function generateBaseSeo(): RedirectResponse
    {
        Artisan::call('kgm:enrich-catalog', ['--seo' => true]);
        Artisan::call('kgm:seo-xml');

        return back()->with('status', 'Template SEO ve XML dosyaları yenilendi.');
    }

    public function clearCache(): RedirectResponse
    {
        Cache::flush();

        return back()->with('status', 'Laravel cache temizlendi; XML ve ürün verileri yeni haliyle okunacak.');
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->firstOrFail();
    }

    /**
     * @return array<string, int>
     */
    private function stats(Tenant $tenant): array
    {
        $active = Product::query()->where('tenant_id', $tenant->id)->where('is_active', true);

        return [
            'active' => (clone $active)->count(),
            'ai' => (clone $active)->whereNotNull('metadata->ai_seo->enriched_at')->count(),
            'missing_ai' => (clone $active)->whereNull('metadata->ai_seo->enriched_at')->count(),
            'with_description' => (clone $active)->whereNotNull('description')->where('description', '<>', '')->count(),
            'with_image' => (clone $active)->where(function ($query): void {
                $query->whereNotNull('image_url')->orWhereNotNull('cdn_image_url');
            })->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function xmlStatus(): array
    {
        $sitemap = public_path('seo/product-sitemap.xml');
        $metadata = public_path('seo/product-metadata.xml');

        return [
            'sitemap_exists' => File::exists($sitemap),
            'metadata_exists' => File::exists($metadata),
            'sitemap_url' => rtrim((string) config('commerce.domains.storefront'), '/').'/seo/product-sitemap.xml',
            'metadata_url' => rtrim((string) config('commerce.domains.storefront'), '/').'/seo/product-metadata.xml',
            'sitemap_updated' => File::exists($sitemap) ? date('Y-m-d H:i:s', File::lastModified($sitemap)) : null,
            'metadata_updated' => File::exists($metadata) ? date('Y-m-d H:i:s', File::lastModified($metadata)) : null,
            'sitemap_size' => File::exists($sitemap) ? File::size($sitemap) : 0,
            'metadata_size' => File::exists($metadata) ? File::size($metadata) : 0,
        ];
    }

    /**
     * @return array{running: bool, pid: ?string}
     */
    private function aiJobStatus(): array
    {
        foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $file) {
            $cmd = str_replace("\0", ' ', @file_get_contents($file) ?: '');
            if (str_contains($cmd, 'kgm:ai-product-seo') && ! str_contains($cmd, 'cmdline')) {
                preg_match('#/proc/([0-9]+)/#', $file, $matches);

                return ['running' => true, 'pid' => $matches[1] ?? null];
            }
        }

        return ['running' => false, 'pid' => null];
    }

    /**
     * @return string[]
     */
    private function tailLog(): array
    {
        $path = storage_path('logs/ai-product-seo.log');
        if (! File::exists($path)) {
            return [];
        }

        $lines = preg_split('/\R/', trim((string) File::get($path))) ?: [];

        return array_slice(array_values(array_filter($lines)), -12);
    }
}
