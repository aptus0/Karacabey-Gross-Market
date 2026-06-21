<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Catalog\SeoXmlGenerator;
use Illuminate\Console\Command;

class GenerateSeoXml extends Command
{
    protected $signature = 'kgm:seo-xml {--tenant=1 : Tenant ID}';

    protected $description = 'Aktif ürünler için ürün sitemap ve SEO metadata XML dosyalarını oluşturur.';

    public function handle(SeoXmlGenerator $generator): int
    {
        $tenantId = (int) $this->option('tenant');
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            $this->error("Tenant bulunamadı: {$tenantId}");

            return self::FAILURE;
        }

        $result = $generator->generate($tenant);

        $this->info("SEO XML üretildi: {$result['products']} ürün");
        $this->line((string) $result['sitemap_url']);
        $this->line((string) $result['metadata_url']);

        return self::SUCCESS;
    }
}
