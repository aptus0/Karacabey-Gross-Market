<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\HomepageBlock;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class MarketingVisualSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'karacabey-gross-market')->first();

        if (! $tenant) {
            return;
        }

        $slides = [
            ['/campaigns/sepet-buyuk-indirim.webp', '/kampanyalar', 10],
            ['/campaigns/haftanin-dev-kampanyalari.webp', '/kampanyalar', 20],
            ['/campaigns/taptaze-meyve-sebze.webp', '/kategori/meyve-sebze', 30],
            ['/campaigns/ev-ihtiyaclari-firsatlari.webp', '/kategori/temizlik', 40],
            ['/campaigns/firsat-kampanyasi.webp', '/kampanyalar', 50],
            ['/campaigns/hosgeldin-indirimi.webp', '/kampanyalar/hosgeldin-indirimi', 60],
        ];

        foreach ($slides as [$imageUrl, $linkUrl, $sortOrder]) {
            HomepageBlock::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'type' => 'carousel_slide', 'image_url' => $imageUrl],
                [
                    'title' => null,
                    'subtitle' => null,
                    'link_url' => $linkUrl,
                    'link_label' => null,
                    'payload' => ['visual_only' => true],
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                    'show_on_mobile' => true,
                    'show_on_web' => true,
                ],
            );
        }

        Campaign::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'hosgeldin-indirimi'],
            [
                'name' => 'Hoş Geldin İndirimi',
                'description' => 'Yeni üyeler ilk alışverişlerinde seçili ürünlerde %15 hoş geldin indirimi kazanır.',
                'body' => '<h2>İlk alışverişinize özel avantaj</h2><p>Karacabey Gross Market ailesine katılan yeni üyeler, seçili ürünlerde ilk siparişlerine özel %15 indirimden yararlanır.</p>',
                'banner_image_url' => '/campaigns/hosgeldin-indirimi.webp',
                'meta_image_url' => '/campaigns/hosgeldin-indirimi.webp',
                'badge_label' => 'Yeni Üyelere Özel',
                'color_hex' => '#FF6A00',
                'discount_type' => 'percent',
                'discount_value' => 15,
                'sort_order' => 1,
                'is_active' => true,
                'show_on_mobile' => true,
                'show_on_web' => true,
                'cta_url' => '/products',
                'content_type' => 'welcome',
                'seo' => [
                    'title' => 'Yeni Üyelere %15 Hoş Geldin İndirimi | Karacabey Gross Market',
                    'description' => 'Karacabey Gross Market yeni üyelerine özel ilk alışverişte %15 hoş geldin indirimi. Taze ürünleri ve günlük ihtiyaçları avantajlı fiyatlarla sipariş edin.',
                    'keywords' => ['hoş geldin indirimi', 'ilk sipariş indirimi', 'Karacabey market kampanyası'],
                ],
            ],
        );
    }
}
