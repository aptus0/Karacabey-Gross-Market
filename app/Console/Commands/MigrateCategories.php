<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan kgm:migrate-categories
 *
 * Canlı veritabanındaki eski kategori isim/slug'larını Migros/Getir tarzına
 * dönüştürür. Ürün-kategori bağlarını korur. Idempotent: tekrar çalıştırılması güvenlidir.
 *
 * Adımlar:
 *   1) Slug + isim yeniden adlandırma (et-tavuk-balik → et-tavuk-sarkuteri vb.)
 *   2) Sadece isim güncellemeleri (slug aynı kalanlar)
 *   3) sarkuteri-kahvaltilik kategorisini sut-kahvaltilik'e birleştir + sil
 *   4) Yeni kategori olarak firin-pastane'yi ekle
 */
class MigrateCategories extends Command
{
    protected $signature = 'kgm:migrate-categories {--dry-run : Hiçbir değişiklik yazma, sadece raporla}';

    protected $description = 'Canlı kategorileri Migros/Getir tarzına geçir (idempotent).';

    /** Slug + isim değişenler (ürün bağı korunur). */
    private const SLUG_RENAMES = [
        'et-tavuk-balik' => ['slug' => 'et-tavuk-sarkuteri', 'name' => 'Et, Tavuk & Şarküteri'],
        'sut-urunleri'   => ['slug' => 'sut-kahvaltilik',    'name' => 'Süt Ürünleri & Kahvaltılık'],
        'bebek-cocuk'    => ['slug' => 'bebek',              'name' => 'Bebek'],
        'evcil-hayvan'   => ['slug' => 'pet-shop',           'name' => 'Pet Shop'],
    ];

    /** Sadece isim güncellemesi (slug stabil). */
    private const NAME_ONLY = [
        'meyve-sebze'   => 'Meyve & Sebze',
        'temel-gida'    => 'Temel Gıda',
        'atistirmalik'  => 'Atıştırmalık & Çikolata',
        'icecek'        => 'İçecekler',
        'donmus-hazir'  => 'Dondurulmuş & Hazır Gıda',
        'temizlik'      => 'Temizlik',
        'kisisel-bakim' => 'Kişisel Bakım',
    ];

    public function handle(): int
    {
        $tenant = Tenant::first();
        if (! $tenant) {
            $this->error('Tenant bulunamadı. Önce php artisan db:seed çalıştırın.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        if ($dry) {
            $this->warn('⚠  DRY-RUN modu — hiçbir değişiklik yazılmayacak.');
        }

        DB::transaction(function () use ($tenant, $dry): void {
            $this->renameSlugs($tenant->id, $dry);
            $this->renameNames($tenant->id, $dry);
            $this->mergeSarkuteriKahvaltilik($tenant->id, $dry);
            $this->createFirinPastane($tenant->id, $dry);
        });

        $this->newLine();
        $this->info('✅ Kategori migrasyonu tamamlandı.');
        $this->newLine();
        $this->comment('Şimdi yeni SEO ve sınıflandırma için:');
        $this->comment('  php artisan kgm:enrich-catalog --categories --classify --seo');

        return self::SUCCESS;
    }

    private function renameSlugs(int $tenantId, bool $dry): void
    {
        $this->info('[1/4] Slug + isim yeniden adlandırılıyor...');

        foreach (self::SLUG_RENAMES as $oldSlug => $new) {
            $cat = Category::query()
                ->where('tenant_id', $tenantId)
                ->where('slug', $oldSlug)
                ->first();

            if (! $cat) {
                $this->line("  ↷ {$oldSlug} — DB'de yok, atlandı");
                continue;
            }

            $this->line("  ✓ {$oldSlug} → {$new['slug']} ({$new['name']})");
            if (! $dry) {
                $cat->update(['slug' => $new['slug'], 'name' => $new['name']]);
            }
        }
    }

    private function renameNames(int $tenantId, bool $dry): void
    {
        $this->info('[2/4] Sadece isim güncellemeleri...');

        foreach (self::NAME_ONLY as $slug => $name) {
            $cat = Category::query()
                ->where('tenant_id', $tenantId)
                ->where('slug', $slug)
                ->first();

            if (! $cat) {
                $this->line("  ↷ {$slug} — DB'de yok, atlandı");
                continue;
            }

            $this->line("  ✓ {$slug} → {$name}");
            if (! $dry) {
                $cat->update(['name' => $name]);
            }
        }
    }

    private function mergeSarkuteriKahvaltilik(int $tenantId, bool $dry): void
    {
        $this->info('[3/4] Şarküteri & Kahvaltılık → Süt Ürünleri & Kahvaltılık birleştirmesi...');

        $sarkuteri = Category::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', 'sarkuteri-kahvaltilik')
            ->first();

        if (! $sarkuteri) {
            $this->line('  ↷ sarkuteri-kahvaltilik zaten yok, atlandı');

            return;
        }

        // sut-kahvaltilik adım 1'de oluşmuş olmalı; dry-run'da hâlâ sut-urunleri olabilir.
        $hedef = Category::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('slug', ['sut-kahvaltilik', 'sut-urunleri'])
            ->first();

        if (! $hedef) {
            $this->warn('  ⚠  Birleştirilecek hedef (sut-kahvaltilik / sut-urunleri) bulunamadı.');

            return;
        }

        $sarkuteriId = $sarkuteri->id;
        $hedefId     = $hedef->id;

        $pivotRows = DB::table('category_product')
            ->where('category_id', $sarkuteriId)
            ->get(['product_id'])
            ->pluck('product_id')
            ->all();

        $count = count($pivotRows);

        if ($dry) {
            $this->line("  [DRY] {$count} ürün bağı → {$hedef->slug}'a taşınacak; sonra sarkuteri-kahvaltilik silinecek.");

            return;
        }

        if ($count > 0) {
            $insertRows = array_map(fn ($pid) => [
                'category_id' => $hedefId,
                'product_id'  => $pid,
                'created_at'  => now(),
                'updated_at'  => now(),
            ], $pivotRows);

            DB::table('category_product')->insertOrIgnore($insertRows);
            DB::table('category_product')->where('category_id', $sarkuteriId)->delete();
        }

        $sarkuteri->delete();
        $this->line("  ✓ {$count} ürün bağı taşındı, sarkuteri-kahvaltilik silindi.");
    }

    private function createFirinPastane(int $tenantId, bool $dry): void
    {
        $this->info('[4/4] Fırın & Pastane kategorisi oluşturuluyor...');

        $exists = Category::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', 'firin-pastane')
            ->exists();

        if ($exists) {
            $this->line('  ↷ firin-pastane zaten var, atlandı');

            return;
        }

        if ($dry) {
            $this->line('  [DRY] firin-pastane oluşturulacak');

            return;
        }

        Category::query()->create([
            'tenant_id'   => $tenantId,
            'slug'        => 'firin-pastane',
            'name'        => 'Fırın & Pastane',
            'description' => 'Taze ekmek, simit, pide, lavaş, bazlama ve pastane ürünleri.',
            'sort_order'  => 40,
            'is_active'   => true,
        ]);

        $this->line('  ✓ firin-pastane oluşturuldu');
    }
}
