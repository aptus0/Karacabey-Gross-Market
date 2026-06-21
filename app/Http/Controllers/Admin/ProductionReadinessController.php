<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\MaintenanceModeService;
use App\Support\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ProductionReadinessController extends Controller
{
    public function __invoke(Request $request, TenantResolver $tenants, MaintenanceModeService $maintenance): View
    {
        $tenant = $tenants->resolve($request);
        $orderStatusCounts = $this->orderStatusCounts($tenant);
        $catalog = $this->catalogStats($tenant);
        $queues = $this->queueStats();
        $seoFiles = $this->seoFiles();
        $configChecks = $this->configChecks();
        $mailRecords = $this->mailRecords();

        $criticalWarnings = collect($configChecks)
            ->merge($catalog['warnings'])
            ->merge($queues['warnings'])
            ->filter(fn (array $check): bool => ($check['level'] ?? 'ok') === 'critical')
            ->values()
            ->all();

        return view('admin.production-readiness.index', [
            'tenant' => $tenant,
            'maintenance' => $maintenance->status($tenant),
            'orderStatusCounts' => $orderStatusCounts,
            'catalog' => $catalog,
            'queues' => $queues,
            'seoFiles' => $seoFiles,
            'configChecks' => $configChecks,
            'mailRecords' => $mailRecords,
            'criticalWarnings' => $criticalWarnings,
            'generatedAt' => now(),
        ]);
    }

    /** @return array<string, int> */
    private function orderStatusCounts(Tenant $tenant): array
    {
        if (! Schema::hasTable('orders')) {
            return [];
        }

        $counts = Order::query()
            ->where('tenant_id', $tenant->id)
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($value): int => (int) $value)
            ->all();

        foreach (OrderStatus::cases() as $status) {
            $counts[$status->value] = $counts[$status->value] ?? 0;
        }

        return $counts;
    }

    /** @return array<string, mixed> */
    private function catalogStats(Tenant $tenant): array
    {
        $warnings = [];
        if (! Schema::hasTable('products')) {
            return [
                'active' => 0,
                'missing_images' => 0,
                'missing_seo' => 0,
                'out_of_stock_active' => 0,
                'warnings' => [[
                    'level' => 'critical',
                    'title' => 'Ürün tablosu bulunamadı',
                    'message' => 'Canlı web vitrin açılmadan önce products tablosu ve migration durumu kontrol edilmeli.',
                ]],
            ];
        }

        $base = Product::query()->where('tenant_id', $tenant->id)->where('is_active', true);
        $active = (clone $base)->count();
        $missingImages = (clone $base)
            ->where(function ($query): void {
                $query->whereNull('image_url')->orWhere('image_url', '')->orWhere('image_url', 'like', '%kgm-logo%');
            })
            ->count();
        $missingSeo = (clone $base)
            ->where(function ($query): void {
                $query->whereNull('seo')->orWhere('seo', '[]')->orWhere('seo', '{}');
            })
            ->count();
        $outOfStockActive = (clone $base)->where('stock_quantity', '<=', 0)->count();

        if ($active < 100) {
            $warnings[] = [
                'level' => 'critical',
                'title' => 'Aktif ürün sayısı düşük',
                'message' => 'Canlı vitrin için katalog import/ERP senkronu tamamlanmadan yayın yapmak riskli.',
            ];
        }
        if ($active > 0 && ($missingImages / max(1, $active)) > 0.15) {
            $warnings[] = [
                'level' => 'warning',
                'title' => 'Ürün görselleri eksik',
                'message' => 'Google görseller ve ürün kartları için gerçek ürün görselleri tamamlanmalı.',
            ];
        }
        if ($missingSeo > 0) {
            $warnings[] = [
                'level' => 'warning',
                'title' => 'SEO alanı boş ürünler var',
                'message' => 'Başlık/açıklama/kategori kelimeleri eksik ürünler organik trafikte zayıf kalır.',
            ];
        }

        return [
            'active' => (int) $active,
            'missing_images' => (int) $missingImages,
            'missing_seo' => (int) $missingSeo,
            'out_of_stock_active' => (int) $outOfStockActive,
            'warnings' => $warnings,
        ];
    }

    /** @return array<string, mixed> */
    private function queueStats(): array
    {
        $warnings = [];
        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $jobs = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $failedNotifications = Schema::hasTable('notification_jobs')
            ? DB::table('notification_jobs')->whereIn('status', ['failed', 'error'])->count()
            : 0;
        $pendingNotifications = Schema::hasTable('notification_jobs')
            ? DB::table('notification_jobs')->whereIn('status', ['pending', 'queued'])->count()
            : 0;

        if ($failedJobs > 0 || $failedNotifications > 0) {
            $warnings[] = [
                'level' => 'critical',
                'title' => 'Kuyrukta hata var',
                'message' => 'Canlı yayın öncesi failed_jobs ve hatalı notification_jobs kayıtları incelenmeli.',
            ];
        }

        return [
            'jobs' => (int) $jobs,
            'failed_jobs' => (int) $failedJobs,
            'pending_notifications' => (int) $pendingNotifications,
            'failed_notifications' => (int) $failedNotifications,
            'warnings' => $warnings,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function seoFiles(): array
    {
        $files = [
            ['name' => 'product-sitemap.xml', 'public' => public_path('seo/product-sitemap.xml'), 'route' => base_path('resources/js/app/seo/product-sitemap.xml/route.ts')],
            ['name' => 'product-images-sitemap.xml', 'public' => public_path('seo/product-images-sitemap.xml'), 'route' => base_path('resources/js/app/seo/product-images-sitemap.xml/route.ts')],
            ['name' => 'product-metadata.xml', 'public' => public_path('seo/product-metadata.xml'), 'route' => null],
            ['name' => 'sitemap.xml', 'public' => public_path('sitemap.xml'), 'route' => base_path('resources/js/app/sitemap.ts')],
            ['name' => 'robots.txt', 'public' => public_path('robots.txt'), 'route' => base_path('resources/js/app/robots.ts')],
        ];

        return array_map(function (array $file): array {
            $publicExists = File::exists($file['public']);
            $routeExists = is_string($file['route']) && File::exists($file['route']);
            $path = $publicExists ? $file['public'] : $file['route'];

            return [
                'name' => $file['name'],
                'exists' => $publicExists || $routeExists,
                'size' => is_string($path) && File::exists($path) ? File::size($path) : 0,
                'updated_at' => is_string($path) && File::exists($path) ? date('d.m.Y H:i', File::lastModified($path)) : null,
                'source' => $publicExists ? 'public' : ($routeExists ? 'next-route' : 'eksik'),
            ];
        }, $files);
    }

    /** @return array<int, array<string, string>> */
    private function configChecks(): array
    {
        $checks = [];
        $add = static function (bool $ok, string $title, string $message, string $criticalMessage = '') use (&$checks): void {
            $checks[] = [
                'level' => $ok ? 'ok' : 'critical',
                'title' => $title,
                'message' => $ok ? $message : ($criticalMessage !== '' ? $criticalMessage : $message),
            ];
        };

        $add(app()->environment('production'), 'APP_ENV', 'Production ortamı aktif.', 'APP_ENV production değil; canlı yayında production olmalı.');
        $add(! (bool) config('app.debug'), 'APP_DEBUG', 'Debug kapalı.', 'APP_DEBUG açık görünüyor; canlı sistemde kapatılmalı.');
        $add((string) config('queue.default') !== 'sync', 'QUEUE_CONNECTION', 'Kuyruk async çalışıyor.', 'QUEUE_CONNECTION sync; bildirim, mail ve ağır işler request içinde yavaşlatır.');
        $add((bool) config('session.secure'), 'SESSION_SECURE_COOKIE', 'Oturum cookie secure.', 'SESSION_SECURE_COOKIE true olmalı.');
        $add((int) env('KGM_MIN_ORDER_CENTS', 35000) === 35000, 'Minimum sepet', 'Minimum sepet 350 TL kuralına bağlı.', 'KGM_MIN_ORDER_CENTS 35000 olmalı.');

        return $checks;
    }

    /** @return array<int, array<string, string>> */
    private function mailRecords(): array
    {
        $domain = config('commerce.primary_domain', 'karacabeygrossmarket.com');
        $mailIp = env('MAIL_SERVER_IPV4', 'SUNUCU_IP_ADRESI');
        $dkimSelector = env('MAIL_DKIM_SELECTOR', 'default');
        $webmailTarget = env('WEBMAIL_TUNNEL_TARGET', 'cloudflare-tunnel-id.cfargotunnel.com');

        return [
            ['type' => 'A', 'name' => 'mail.' . $domain, 'value' => $mailIp, 'mode' => 'DNS only'],
            ['type' => 'MX', 'name' => $domain, 'value' => '10 mail.' . $domain, 'mode' => 'DNS only'],
            ['type' => 'TXT', 'name' => $domain, 'value' => 'v=spf1 mx a:mail.' . $domain . ' ~all', 'mode' => 'DNS only'],
            ['type' => 'TXT', 'name' => $dkimSelector . '._domainkey.' . $domain, 'value' => 'v=DKIM1; k=rsa; p=DKIM_PUBLIC_KEY', 'mode' => 'DNS only'],
            ['type' => 'TXT', 'name' => '_dmarc.' . $domain, 'value' => 'v=DMARC1; p=quarantine; rua=mailto:support@' . $domain . '; adkim=s; aspf=s', 'mode' => 'DNS only'],
            ['type' => 'CNAME', 'name' => 'webmail.' . $domain, 'value' => $webmailTarget, 'mode' => 'Proxied / Tunnel'],
        ];
    }
}
