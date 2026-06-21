<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\HomepageBlock;
use App\Models\MarketingSetting;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Tenant ─────────────────────────────────────────────────────
        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => 'karacabey-gross-market'],
            [
                'name'      => 'Karacabey Gross Market',
                'domain'    => 'karacabeygrossmarket.com',
                'is_active' => true,
                'settings'  => [
                    'currency'      => 'TRY',
                    'locale'        => 'tr_TR',
                    'support_phone' => '+90 224 000 00 00',
                    'support_email' => 'destek@karacabeygrossmarket.com',
                ],
            ],
        );

        // ── Admin Kullanıcı ────────────────────────────────────────────
        User::query()->updateOrCreate(
            ['email' => 'erkur@kgm.com'],
            [
                'name'              => 'Erkur Admin',
                'password'          => Hash::make('132456@Erkur!'),
                'is_admin'          => true,
                'email_verified_at' => now(),
            ],
        );

        // ── Kategoriler ────────────────────────────────────────────────
        // Detaylı SEO + açıklama için: php artisan kgm:enrich-catalog --categories
        $categoryDefs = [
            ['name' => 'Meyve & Sebze',                'slug' => 'meyve-sebze',         'sort_order' => 10,  'description' => 'Günlük taze meyve, sebze, yeşillik ve mantar çeşitleri.'],
            ['name' => 'Et, Tavuk & Şarküteri',        'slug' => 'et-tavuk-sarkuteri',  'sort_order' => 20,  'description' => 'Taze kırmızı et, tavuk, balık, sucuk, salam ve şarküteri ürünleri.'],
            ['name' => 'Süt Ürünleri & Kahvaltılık',   'slug' => 'sut-kahvaltilik',     'sort_order' => 30,  'description' => 'Süt, yoğurt, peynir, zeytin, reçel, bal ve kahvaltılık çeşitleri.'],
            ['name' => 'Fırın & Pastane',              'slug' => 'firin-pastane',       'sort_order' => 40,  'description' => 'Taze ekmek, simit, pide, lavaş, bazlama ve pastane ürünleri.'],
            ['name' => 'Temel Gıda',                   'slug' => 'temel-gida',          'sort_order' => 50,  'description' => 'Pirinç, bulgur, makarna, bakliyat, yağ, şeker, un ve mutfak stok ürünleri.'],
            ['name' => 'Atıştırmalık & Çikolata',      'slug' => 'atistirmalik',        'sort_order' => 60,  'description' => 'Çikolata, bisküvi, gofret, cips, kuruyemiş ve atıştırmalık ürünler.'],
            ['name' => 'İçecekler',                    'slug' => 'icecek',              'sort_order' => 70,  'description' => 'Su, maden suyu, meyve suyu, gazlı içecek, çay ve kahve.'],
            ['name' => 'Dondurulmuş & Hazır Gıda',     'slug' => 'donmus-hazir',        'sort_order' => 80,  'description' => 'Dondurulmuş sebze, hazır yemek, pizza, börek ve pratik gıdalar.'],
            ['name' => 'Temizlik',                     'slug' => 'temizlik',            'sort_order' => 90,  'description' => 'Deterjan, bulaşık, ev temizliği, tuvalet kağıdı ve hijyen ürünleri.'],
            ['name' => 'Kişisel Bakım',                'slug' => 'kisisel-bakim',       'sort_order' => 100, 'description' => 'Şampuan, duş jeli, deodorant, diş macunu ve kişisel bakım ürünleri.'],
            ['name' => 'Bebek',                        'slug' => 'bebek',               'sort_order' => 110, 'description' => 'Bebek maması, bez, ıslak mendil, biberon ve bebek bakım ürünleri.'],
            ['name' => 'Pet Shop',                     'slug' => 'pet-shop',            'sort_order' => 120, 'description' => 'Kedi, köpek ve evcil hayvan maması ile bakım ürünleri.'],
        ];

        foreach ($categoryDefs as $def) {
            Category::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $def['slug']],
                [
                    'name'        => $def['name'],
                    'description' => $def['description'],
                    'sort_order'  => $def['sort_order'],
                    'seo'         => [
                        'title'       => $def['name'].' | Karacabey Gross Market',
                        'description' => $def['description'].' Karacabey Gross Market\'te hızlı teslimat ve avantajlı fiyatlarla online sipariş.',
                        'keywords'    => ['Karacabey market', $def['name'], 'online market', 'Bursa market siparişi'],
                    ],
                    'is_active'   => true,
                ],
            );
        }

        // ── Statik Sayfalar ────────────────────────────────────────────
        $pages = [
            [
                'slug'            => 'teslimat-kosullari',
                'title'           => 'Teslimat Koşulları',
                'group'           => 'support',
                'body'            => 'Karacabey Gross Market siparişleri seçili bölgelere planlanmış teslimatla ulaştırır.',
                'seo_title'       => 'Teslimat Koşulları | Karacabey Gross Market',
                'seo_description' => 'Karacabey Gross Market teslimat saatleri, minimum sepet ve sipariş süreci.',
            ],
            [
                'slug'            => 'gizlilik-politikasi',
                'title'           => 'Gizlilik Politikası',
                'group'           => 'legal',
                'body'            => 'Kişisel verileriniz 6698 sayılı KVKK kapsamında korunmakta olup üçüncü taraflarla paylaşılmamaktadır.',
                'seo_title'       => 'Gizlilik Politikası | Karacabey Gross Market',
                'seo_description' => 'Karacabey Gross Market kişisel veri işleme ve gizlilik politikası.',
            ],
            [
                'slug'            => 'iade-iade-sartlari',
                'title'           => 'İade & Değişim Şartları',
                'group'           => 'support',
                'body'            => 'Teslimattan itibaren 2 iş günü içinde hasarlı veya eksik ürünler için iade talebinde bulunabilirsiniz.',
                'seo_title'       => 'İade & Değişim | Karacabey Gross Market',
                'seo_description' => 'Karacabey Gross Market iade ve değişim koşulları.',
            ],
        ];

        foreach ($pages as $page) {
            Page::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $page['slug']],
                [
                    'title'           => $page['title'],
                    'group'           => $page['group'],
                    'body'            => $page['body'],
                    'seo_title'       => $page['seo_title'],
                    'seo_description' => $page['seo_description'],
                    'meta_image_url'  => '/seo/og-default.png',
                    'is_published'    => true,
                    'published_at'    => now(),
                ],
            );
        }

        // ── Navigasyon ─────────────────────────────────────────────────
        $navItems = [
            ['placement' => 'header',        'label' => 'Ana Sayfa',          'url' => '/',                     'icon' => 'home',           'sort_order' => 10],
            ['placement' => 'header',        'label' => 'Kategoriler',        'url' => '/kategoriler',          'icon' => 'grid',           'sort_order' => 20],
            ['placement' => 'header',        'label' => 'Kampanyalar',        'url' => '/kampanyalar',          'icon' => 'tag',            'sort_order' => 30],
            ['placement' => 'header',        'label' => 'Kargo Takip',        'url' => '/kargo-takip',          'icon' => 'package-search', 'sort_order' => 40],
            ['placement' => 'footer_support','label' => 'Teslimat Koşulları', 'url' => '/teslimat-kosullari',   'icon' => 'truck',          'sort_order' => 10],
            ['placement' => 'footer_support','label' => 'İade & Değişim',     'url' => '/iade-iade-sartlari',   'icon' => 'refresh-cw',     'sort_order' => 20],
            ['placement' => 'footer_legal',  'label' => 'Gizlilik Politikası','url' => '/gizlilik-politikasi',  'icon' => 'shield',         'sort_order' => 10],
        ];

        foreach ($navItems as $item) {
            NavigationItem::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'placement' => $item['placement'], 'url' => $item['url']],
                [
                    'label'      => $item['label'],
                    'icon'       => $item['icon'],
                    'sort_order' => $item['sort_order'],
                    'is_active'  => true,
                ],
            );
        }

        // ── Ana Sayfa Blokları ─────────────────────────────────────────
        HomepageBlock::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'type' => 'hero'],
            [
                'title'      => 'Karacabey Gross Market',
                'subtitle'   => 'Yerel market alışverişini hızlı teslimat ve güvenilir stokla yaşayın.',
                'image_url'  => '/seo/og-default.png',
                'link_url'   => '/kategoriler',
                'link_label' => 'Alışverişe Başla',
                'payload'    => [],
                'sort_order' => 1,
                'is_active'  => true,
            ],
        );

        // ── Pazarlama Ayarları ─────────────────────────────────────────
        MarketingSetting::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'announcement_text' => null,
                'extra'             => [
                    'merchant_feed' => '/google-merchant.xml',
                    'sitemap'       => '/sitemap.xml',
                ],
            ],
        );

        $this->call(MarketingVisualSeeder::class);
    }
}
