<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ProductionHealthCheck extends Command
{
    protected $signature = 'kgm:production-health {--json : JSON formatında çıktı ver}';

    protected $description = 'Canlı yayın öncesi web/panel/SEO/mail/kuyruk için güvenli yerel sağlık kontrolü yapar.';

    public function handle(): int
    {
        $checks = $this->buildChecks();
        $failed = collect($checks)->where('status', 'fail')->values();
        $warnings = collect($checks)->where('status', 'warning')->values();

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => $failed->isEmpty(),
                'failed_count' => $failed->count(),
                'warning_count' => $warnings->count(),
                'checks' => $checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $failed->isEmpty() ? self::SUCCESS : self::FAILURE;
        }

        $this->info('KGM Production Health Check');
        $this->table(['Durum', 'Kontrol', 'Mesaj'], array_map(
            fn (array $check): array => [strtoupper($check['status']), $check['name'], $check['message']],
            $checks
        ));

        if ($failed->isNotEmpty()) {
            $this->error('Kritik kontroller başarısız. Canlı deploy öncesi düzeltin.');

            return self::FAILURE;
        }

        if ($warnings->isNotEmpty()) {
            $this->warn('Kritik hata yok; ama uyarılar canlı kaliteyi etkileyebilir.');
        } else {
            $this->info('Tüm kontroller başarılı görünüyor.');
        }

        return self::SUCCESS;
    }

    /** @return array<int, array{name:string,status:string,message:string}> */
    private function buildChecks(): array
    {
        $checks = [];
        $checks[] = $this->check(app()->environment('production'), 'APP_ENV', 'production olmalı.', 'APP_ENV production.');
        $checks[] = $this->check(! (bool) config('app.debug'), 'APP_DEBUG', 'Canlıda debug kapalı olmalı.', 'Debug kapalı.');
        $checks[] = $this->check((string) config('queue.default') !== 'sync', 'Queue', 'QUEUE_CONNECTION sync olmamalı.', 'Queue async çalışıyor.');
        $checks[] = $this->check((bool) config('session.secure'), 'Secure session', 'SESSION_SECURE_COOKIE true olmalı.', 'Secure session aktif.');
        $checks[] = $this->check((int) env('KGM_MIN_ORDER_CENTS', 35000) === 35000, 'Minimum sepet', 'Minimum sepet 350 TL / 35000 kuruş olmalı.', 'Minimum sepet 350 TL.');

        $pendingMigrations = $this->pendingMigrations();
        $checks[] = $this->check(
            $pendingMigrations === [],
            'Pending migrations',
            'Bekleyen migration var: ' . implode(', ', array_slice($pendingMigrations, 0, 8)) . (count($pendingMigrations) > 8 ? '...' : ''),
            'Bekleyen migration yok.'
        );

        $failedJobs = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;
        $checks[] = $this->check($failedJobs === 0, 'Failed jobs', "failed_jobs tablosunda {$failedJobs} kayıt var.", 'Failed job yok.');

        $activeProducts = Schema::hasTable('products') ? (int) Product::query()->where('is_active', true)->count() : 0;
        $checks[] = $this->check($activeProducts > 100, 'Aktif ürün', "Aktif ürün sayısı düşük: {$activeProducts}.", "Aktif ürün sayısı: {$activeProducts}.");

        $missingImages = 0;
        if (Schema::hasTable('products')) {
            $missingImages = (int) Product::query()
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query->whereNull('image_url')->orWhere('image_url', '')->orWhere('image_url', 'like', '%kgm-logo%');
                })
                ->count();
        }
        $checks[] = [
            'name' => 'Ürün görselleri',
            'status' => $missingImages > 0 ? 'warning' : 'ok',
            'message' => $missingImages > 0 ? "Görseli eksik aktif ürün: {$missingImages}." : 'Aktif ürünlerde görsel eksikliği görünmüyor.',
        ];

        $seoTargets = [
            'seo/product-sitemap.xml' => [public_path('seo/product-sitemap.xml'), base_path('resources/js/app/seo/product-sitemap.xml/route.ts')],
            'seo/product-images-sitemap.xml' => [public_path('seo/product-images-sitemap.xml'), base_path('resources/js/app/seo/product-images-sitemap.xml/route.ts')],
            'robots.txt' => [public_path('robots.txt'), base_path('resources/js/app/robots.ts')],
        ];
        foreach ($seoTargets as $name => $paths) {
            $exists = collect($paths)->contains(fn (string $path): bool => File::exists($path) && File::size($path) > 0);
            $checks[] = $this->check($exists, $name, 'SEO dosyası veya Next route eksik.', 'SEO route/dosyası mevcut.');
        }

        $mailHost = env('MAIL_HOST');
        $checks[] = [
            'name' => 'Mail host',
            'status' => $mailHost ? 'ok' : 'warning',
            'message' => $mailHost ? "MAIL_HOST tanımlı: {$mailHost}." : 'MAIL_HOST tanımlı değil; web bildirim e-postaları gönderilemeyebilir.',
        ];

        return $checks;
    }

    /** @return array<int, string> */
    private function pendingMigrations(): array
    {
        if (! Schema::hasTable('migrations')) {
            return collect(File::files(database_path('migrations')))
                ->map(fn (\SplFileInfo $file): string => pathinfo($file->getFilename(), PATHINFO_FILENAME))
                ->values()
                ->all();
        }

        $ran = DB::table('migrations')->pluck('migration')->all();

        return collect(File::files(database_path('migrations')))
            ->map(fn (\SplFileInfo $file): string => pathinfo($file->getFilename(), PATHINFO_FILENAME))
            ->diff($ran)
            ->values()
            ->all();
    }

    /** @return array{name:string,status:string,message:string} */
    private function check(bool $condition, string $name, string $failureMessage, string $successMessage): array
    {
        return [
            'name' => $name,
            'status' => $condition ? 'ok' : 'fail',
            'message' => $condition ? $successMessage : $failureMessage,
        ];
    }
}
