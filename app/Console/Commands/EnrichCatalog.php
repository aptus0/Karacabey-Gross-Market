<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * php artisan kgm:enrich-catalog
 *
 * 1) Canonical KGM kategorilerini oluşturur / günceller
 * 2) Ürünleri isim bazlı anahtar kelimelerle akıllıca kategorize eder
 * 3) Barkodu olmayan ürünlere EAN-13 üretir
 * 4) Her ürünün SEO JSON alanını (title / description / keywords) doldurur
 */
class EnrichCatalog extends Command
{
    protected $signature = 'kgm:enrich-catalog
                                {--categories : Sadece kategorileri güncelle}
                                {--classify   : Sadece ürün-kategori eşlemesini güncelle}
                                {--barcode    : Sadece barkod eksiklerini doldur}
                                {--seo        : Sadece SEO alanlarını güncelle}
                                {--dry-run    : Hiçbir şey kaydetme, sadece raporla}
                                {--chunk=500  : Toplu işlem boyutu}';

    protected $description = 'KGM katalog zenginleştirme: kategoriler, sınıflandırma, barkod, SEO';

    private bool $dry = false;

    private Tenant $tenant;

    /* ── Canonical KGM kategorileri ─────────────────────────────────── */
    private const CATEGORIES = [
        [
            'name'        => 'Meyve & Sebze',
            'slug'        => 'meyve-sebze',
            'sort_order'  => 10,
            'description' => 'Günlük taze meyve, sebze, yeşillik ve mantar çeşitleri.',
            'seo_title'   => 'Taze Meyve & Sebze | Karacabey Gross Market',
            'seo_desc'    => 'Taze meyve, sebze ve yeşillikler. Karacabey Gross Market\'te günlük taze ürünler, indirimli fiyat ve hızlı online sipariş.',
            'keywords'    => ['taze meyve', 'taze sebze', 'meyve siparişi', 'sebze siparişi', 'organik ürün', 'Karacabey market'],
        ],
        [
            'name'        => 'Et, Tavuk & Şarküteri',
            'slug'        => 'et-tavuk-sarkuteri',
            'sort_order'  => 20,
            'description' => 'Taze kırmızı et, tavuk, balık, sucuk, salam ve şarküteri ürünleri. Güvenilir soğuk zincirle teslimat.',
            'seo_title'   => 'Et, Tavuk & Şarküteri | Karacabey Gross Market',
            'seo_desc'    => 'Taze et, tavuk, balık, sucuk, salam ve şarküteri ürünleri. Karacabey Gross Market\'te soğuk zincirle güvenli teslimat.',
            'keywords'    => ['taze et', 'tavuk siparişi', 'sucuk', 'salam', 'şarküteri', 'kıyma', 'balık siparişi', 'Karacabey market'],
        ],
        [
            'name'        => 'Süt Ürünleri & Kahvaltılık',
            'slug'        => 'sut-kahvaltilik',
            'sort_order'  => 30,
            'description' => 'Süt, yoğurt, peynir, zeytin, reçel, bal, tereyağı ve kahvaltılık çeşitleri.',
            'seo_title'   => 'Süt Ürünleri & Kahvaltılık | Karacabey Gross Market',
            'seo_desc'    => 'Süt, yoğurt, peynir, zeytin, reçel ve kahvaltılık çeşitleri. Karacabey Gross Market\'te günlük taze ürünler, hızlı teslimat.',
            'keywords'    => ['süt siparişi', 'yoğurt market', 'peynir çeşidi', 'zeytin siparişi', 'kahvaltılık', 'reçel bal', 'Karacabey market'],
        ],
        [
            'name'        => 'Fırın & Pastane',
            'slug'        => 'firin-pastane',
            'sort_order'  => 40,
            'description' => 'Taze ekmek, simit, pide, lavaş, bazlama, börek ve pastane ürünleri.',
            'seo_title'   => 'Fırın & Pastane Ürünleri | Karacabey Gross Market',
            'seo_desc'    => 'Taze ekmek, simit, pide, lavaş ve pastane ürünleri. Karacabey Gross Market\'te günlük taze fırın ürünleri.',
            'keywords'    => ['ekmek siparişi', 'simit market', 'pide', 'lavaş', 'taze ekmek', 'fırın ürünleri', 'Karacabey market'],
        ],
        [
            'name'        => 'Temel Gıda',
            'slug'        => 'temel-gida',
            'sort_order'  => 50,
            'description' => 'Pirinç, bulgur, makarna, bakliyat, yağ, şeker, un ve mutfak stok ürünleri.',
            'seo_title'   => 'Temel Gıda | Karacabey Gross Market',
            'seo_desc'    => 'Pirinç, bulgur, makarna, bakliyat, yağ ve kuru gıda. Karacabey Gross Market\'te büyük boy, ekonomik fiyat.',
            'keywords'    => ['bakliyat siparişi', 'pirinç market', 'makarna online', 'kuru gıda', 'zeytinyağı', 'Karacabey market'],
        ],
        [
            'name'        => 'Atıştırmalık & Çikolata',
            'slug'        => 'atistirmalik',
            'sort_order'  => 60,
            'description' => 'Çikolata, bisküvi, gofret, cips, kuruyemiş ve atıştırmalık ürünler.',
            'seo_title'   => 'Atıştırmalık & Çikolata | Karacabey Gross Market',
            'seo_desc'    => 'Çikolata, bisküvi, cips ve kuruyemiş çeşitleri. Karacabey Gross Market\'te kampanyalı atıştırmalık ürünler.',
            'keywords'    => ['çikolata siparişi', 'bisküvi market', 'cips online', 'kuruyemiş', 'atıştırmalık', 'Karacabey market'],
        ],
        [
            'name'        => 'İçecekler',
            'slug'        => 'icecek',
            'sort_order'  => 70,
            'description' => 'Su, maden suyu, meyve suyu, gazlı içecek, enerji içeceği, çay ve kahve.',
            'seo_title'   => 'İçecekler | Karacabey Gross Market',
            'seo_desc'    => 'Su, meyve suyu, kola, enerji içeceği, çay ve kahve. Karacabey Gross Market\'te içecek çeşitleri uygun fiyatla.',
            'keywords'    => ['su siparişi', 'meyve suyu market', 'içecek online', 'çay kahve', 'enerji içeceği', 'Karacabey market'],
        ],
        [
            'name'        => 'Dondurulmuş & Hazır Gıda',
            'slug'        => 'donmus-hazir',
            'sort_order'  => 80,
            'description' => 'Dondurulmuş sebze, hazır yemek, pizza, börek ve pratik gıdalar.',
            'seo_title'   => 'Dondurulmuş & Hazır Gıda | Karacabey Gross Market',
            'seo_desc'    => 'Dondurulmuş sebze, hazır yemek, pizza ve pratik gıdalar. Karacabey Gross Market\'te hızlı hazır çözümler.',
            'keywords'    => ['dondurulmuş sebze', 'hazır yemek market', 'pizza online', 'pratik gıda', 'Karacabey market'],
        ],
        [
            'name'        => 'Temizlik',
            'slug'        => 'temizlik',
            'sort_order'  => 90,
            'description' => 'Çamaşır deterjanı, bulaşık, ev temizliği, tuvalet kağıdı ve hijyen ürünleri.',
            'seo_title'   => 'Temizlik Ürünleri | Karacabey Gross Market',
            'seo_desc'    => 'Deterjan, temizlik ürünleri ve hijyen malzemeleri. Karacabey Gross Market\'te ekonomik paket, hızlı teslimat.',
            'keywords'    => ['deterjan siparişi', 'temizlik ürünleri online', 'tuvalet kağıdı', 'bulaşık deterjanı', 'Karacabey market'],
        ],
        [
            'name'        => 'Kişisel Bakım',
            'slug'        => 'kisisel-bakim',
            'sort_order'  => 100,
            'description' => 'Şampuan, duş jeli, deodorant, diş macunu ve kişisel bakım ürünleri.',
            'seo_title'   => 'Kişisel Bakım Ürünleri | Karacabey Gross Market',
            'seo_desc'    => 'Şampuan, duş jeli, diş macunu ve deodorant. Karacabey Gross Market\'te kişisel bakım ürünleri uygun fiyatla.',
            'keywords'    => ['şampuan siparişi', 'diş macunu market', 'kişisel bakım online', 'deodorant', 'Karacabey market'],
        ],
        [
            'name'        => 'Bebek',
            'slug'        => 'bebek',
            'sort_order'  => 110,
            'description' => 'Bebek maması, bez, ıslak mendil, biberon ve bebek bakım ürünleri.',
            'seo_title'   => 'Bebek Ürünleri | Karacabey Gross Market',
            'seo_desc'    => 'Bebek maması, bez ve ıslak mendil. Karacabey Gross Market\'te güvenilir bebek ürünleri hızlı teslimatla.',
            'keywords'    => ['bebek bezi siparişi', 'bebek maması market', 'ıslak mendil', 'bebek bakım', 'Karacabey market'],
        ],
        [
            'name'        => 'Pet Shop',
            'slug'        => 'pet-shop',
            'sort_order'  => 120,
            'description' => 'Kedi, köpek ve evcil hayvan maması ile bakım ürünleri.',
            'seo_title'   => 'Pet Shop Ürünleri | Karacabey Gross Market',
            'seo_desc'    => 'Kedi maması, köpek maması ve evcil hayvan bakım ürünleri. Karacabey Gross Market\'te uygun fiyat.',
            'keywords'    => ['kedi maması market', 'köpek maması siparişi', 'pet shop', 'evcil hayvan ürünleri', 'Karacabey market'],
        ],
    ];

    /* ── Keyword → kategori slug eşlemesi ──────────────────────────── */
    private const KEYWORD_RULES = [
        'meyve-sebze' => [
            'elma', 'armut', 'portakal', 'mandalina', 'limon', 'çilek', 'kiraz', 'şeftali',
            'kayısı', 'erik', 'karpuz', 'kavun', 'üzüm', 'muz', 'nar', 'ananas', 'avokado',
            'domates', 'salatalık', 'biber', 'patlıcan', 'kabak', 'havuç', 'soğan', 'sarmısak',
            'sarımsak', 'patates', 'marul', 'ıspanak', 'ispanak', 'brokoli', 'karnabahar',
            'lahana', 'pırasa', 'kereviz', 'maydanoz', 'dereotu', 'nane', 'roka', 'mantar',
            'bamya', 'fasulye taze', 'bezelye', 'meyve', 'sebze', 'yeşillik', 'taze',
        ],
        'et-tavuk-sarkuteri' => [
            ' et ', 'kıyma', 'biftek', 'antrikot', 'pirzola', 'kuzu', 'dana', 'koyun', 'bonfile',
            'kaburga', 'incik', 'but', 'gerdan', 'kavurma', 'hindi', 'tavuk', 'piliç', 'göğüs',
            'but fileto', 'kanat', 'bütün tavuk', 'balık', 'levrek', 'çipura', 'somon', 'sardalya',
            'ton balığı', 'kalkan', 'hamsi', 'istavrit', 'karides', 'midye', 'ahtapot',
            'sucuk', 'pastırma', 'salam', 'sosis', 'jambon', 'kangal', 'baton', 'şarküteri',
        ],
        'sut-kahvaltilik' => [
            'süt', 'tam yağlı süt', 'yarım yağlı', 'yağsız süt', 'yoğurt', 'ayran',
            'kefir', 'tereyağ', 'tereyağı', 'krema', 'kaymak', 'çökelek', 'taze peynir',
            'sütlü', 'mandıra',
            'peynir', 'beyaz peynir', 'kaşar', 'tulum', 'lor', 'labne', 'cheddar',
            'zeytin', 'siyah zeytin', 'yeşil zeytin', 'reçel', 'bal', 'marmelat',
            'tahin', 'pekmez', 'yumurta', 'kahvaltılık', 'serpme', 'krem peynir',
        ],
        'firin-pastane' => [
            'ekmek', 'tam buğday ekmeği', 'tost ekmeği', 'sandviç ekmeği', 'hamburger ekmeği',
            'baget', 'simit', 'açma', 'pide', 'lavaş', 'bazlama', 'yufka', 'galeta',
            'galeta unu', 'kruvasan', 'donut', 'milföy', 'pastane', 'tatlı pastane',
            'baklava', 'kadayıf', 'şekerpare', 'revani', 'tulumba', 'rulo pasta', 'tart',
        ],
        'temel-gida' => [
            'pirinç', 'bulgur', 'makarna', 'erişte', 'un', 'nişasta', 'irmik',
            'şeker', 'tuz', 'zeytinyağı', 'ayçiçek yağı', 'mısır yağı', 'tereyağı marka',
            'salça', 'domates salçası', 'biber salçası', 'sirke', 'baharat', 'karabiber',
            'kimyon', 'pul biber', 'kırmızı biber', 'safran', 'tarçın', 'vanilya',
            'nohut', 'mercimek', 'kırmızı mercimek', 'yeşil mercimek', 'kuru fasulye',
            'barbunya', 'börülce', 'soya', 'bezelye kuru', 'bakliyat',
            'konserve', 'domates konserve', 'mısır', 'bezelye konserve', 'mantar konserve',
        ],
        'atistirmalik' => [
            'çikolata', 'sütlü çikolata', 'bitter', 'fındıklı çikolata', 'pralin',
            'bisküvi', 'gofret', 'wafer', 'kraker', 'cips', 'patates cipsi', 'mısır cipsi',
            'popcorn', 'patlak mısır', 'kuruyemiş', 'fındık', 'fıstık', 'badem', 'ceviz',
            'kaju', 'antep fıstığı', 'üzüm kuru', 'kayısı kuru', 'şekerleme', 'lolipop',
            'sakız', 'jelibön', 'lokum', 'helva', 'kek', 'pasta', 'muffin', 'kurabiye',
            'dondurma', 'bar çikolata',
        ],
        'icecek' => [
            'su', 'kaynak suyu', 'maden suyu', 'soda', 'kola', 'pepsi', 'cola', 'fanta',
            'sprite', 'gazoz', 'meyve suyu', 'nektar', 'smoothie', 'enerji içeceği',
            'ayran içecek', 'limonata', 'şalgam', 'buzlu çay', 'ice tea',
            'çay', 'demleme çay', 'poşet çay', 'bitki çayı', 'ıhlamur', 'papatya',
            'kahve', 'nescafe', 'hazır kahve', 'filtre kahve', 'türk kahvesi', 'kapüçino',
            'kakao', 'sıcak çikolata', 'boza',
        ],
        'donmus-hazir' => [
            'dondurulmuş', 'donmuş', 'frozen', 'hazır yemek', 'pizza', 'lazanya',
            'börek hazır', 'poğaça hazır', 'köfte hazır', 'şiş hazır', 'çiğ köfte',
            'sebze karışım', 'bezelye dondurulmuş', 'mısır dondurulmuş', 'ıspanak dondurulmuş',
        ],
        'temizlik' => [
            'deterjan', 'çamaşır deterjanı', 'toz deterjan', 'sıvı deterjan', 'kapsül',
            'bulaşık', 'bulaşık deterjanı', 'bulaşık jeli', 'bulaşık tableti',
            'yüzey temizleyici', 'banyo temizleyici', 'klozet', 'tuvalet',
            'tuvalet kağıdı', 'kağıt havlu', 'peçete', 'islak mendil ev',
            'çöp poşeti', 'çöp torbası', 'bulaşık süngeri', 'bez temizlik',
            'cam temizleyici', 'genel temizlik', 'yağ çözücü', 'kireç çözücü',
            'yumuşatıcı', 'ağartıcı', 'çamaşır suyu', 'kolonya hijyen',
        ],
        'kisisel-bakim' => [
            'şampuan', 'saç kremi', 'saç bakım', 'duş jeli', 'vücut şampuanı',
            'sabun', 'el sabunu', 'sıvı sabun', 'deodorant', 'roll-on', 'parfüm',
            'kolonya kişisel', 'diş macunu', 'diş fırçası', 'ağız bakım', 'ağız suyu',
            'yüz kremi', 'nemlendirici', 'güneş kremi', 'el kremi', 'losyon',
            'tıraş', 'tras köpüğü', 'tras jeli', 'tras bıçağı', 'epilasyon',
            'makyaj', 'fondöten', 'ruj', 'maskara', 'kalem göz', 'oje',
            'pamuk', 'kulak temizleyici', 'pedikür', 'manikür',
        ],
        'bebek' => [
            'bebek maması', 'mama', 'devam sütü', 'biberon', 'emzik', 'bez', 'bebek bezi',
            'ıslak mendil bebek', 'pişik kremi', 'bebek şampuanı', 'bebek sabunu',
            'bebek losyonu', 'anne sütü', 'göğüs pompası', 'çocuk gıdası',
            'meyve püresi bebek', 'sebze püresi bebek', 'çocuk bisküvisi',
        ],
        'pet-shop' => [
            'kedi maması', 'kedi', 'köpek maması', 'köpek', 'evcil hayvan', 'pet food', 'pet shop',
            'kuş yemi', 'balık yemi', 'kedi kumu', 'köpek tasması', 'kedi oyuncak',
        ],
    ];

    /* ────────────────────────────────────────────────────────────────── */

    public function handle(): int
    {
        $tenant = Tenant::first();
        if (! $tenant) {
            $this->error('Tenant bulunamadı. Önce php artisan db:seed çalıştırın.');

            return self::FAILURE;
        }
        $this->tenant = $tenant;
        $this->dry = (bool) $this->option('dry-run');

        if ($this->dry) {
            $this->warn('⚠  DRY-RUN modu — hiçbir değişiklik kaydedilmeyecek.');
        }

        $all = ! $this->option('categories')
            && ! $this->option('classify')
            && ! $this->option('barcode')
            && ! $this->option('seo');

        if ($all || $this->option('categories')) {
            $this->upsertCategories();
        }
        if ($all || $this->option('classify')) {
            $this->classifyProducts();
        }
        if ($all || $this->option('barcode')) {
            $this->generateBarcodes();
        }
        if ($all || $this->option('seo')) {
            $this->enrichSeo();
        }

        $this->newLine();
        $this->info('✅ Katalog zenginleştirme tamamlandı.');

        return self::SUCCESS;
    }

    /* ── 1. Kategoriler ─────────────────────────────────────────────── */

    private function upsertCategories(): void
    {
        $this->info('[1/4] Kategoriler güncelleniyor...');

        foreach (self::CATEGORIES as $def) {
            if ($this->dry) {
                $this->line("  [DRY] {$def['name']} ({$def['slug']})");
                continue;
            }

            Category::query()->updateOrCreate(
                [
                    'tenant_id' => $this->tenant->id,
                    'slug'      => $def['slug'],
                ],
                [
                    'name'        => $def['name'],
                    'description' => $def['description'],
                    'sort_order'  => $def['sort_order'],
                    'is_active'   => true,
                    'seo'         => [
                        'title'       => $def['seo_title'],
                        'description' => $def['seo_desc'],
                        'keywords'    => $def['keywords'],
                    ],
                ],
            );

            $this->line("  ✓ {$def['name']}");
        }

        $this->info('  → '.count(self::CATEGORIES).' kategori işlendi.');
    }

    /* ── 2. Akıllı sınıflandırma ─────────────────────────────────────── */

    private function classifyProducts(): void
    {
        $this->info('[2/4] Ürünler akıllıca kategorize ediliyor...');

        // slug → Category.id haritası
        $slugToId = Category::query()
            ->where('tenant_id', $this->tenant->id)
            ->whereIn('slug', array_keys(self::KEYWORD_RULES))
            ->get(['id', 'slug'])
            ->pluck('id', 'slug')
            ->all();

        if (empty($slugToId)) {
            $this->warn('  Önce --categories seçeneğiyle kategoriler oluşturulmalı.');

            return;
        }

        $classified = 0;
        $skipped    = 0;
        $chunk      = (int) ($this->option('chunk') ?: 500);

        Product::query()
            ->where('tenant_id', $this->tenant->id)
            ->select(['id', 'name', 'brand'])
            ->chunk($chunk, function ($products) use ($slugToId, &$classified, &$skipped): void {
                $pairs = [];

                foreach ($products as $product) {
                    $catSlug = $this->detectCategory($product->name, $product->brand ?? '');
                    $catId = $catSlug ? ($slugToId[$catSlug] ?? null) : null;

                    if (! $catId) {
                        $skipped++;
                        continue;
                    }

                    $pairs[] = [
                        'category_id' => $catId,
                        'product_id'  => $product->id,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ];
                    $classified++;
                }

                if (! empty($pairs) && ! $this->dry) {
                    // Mevcut atamaları temizle; sadece kategorize edilenleri
                    $productIds = array_column($pairs, 'product_id');
                    DB::table('category_product')->whereIn('product_id', $productIds)->delete();
                    DB::table('category_product')->insertOrIgnore($pairs);
                }
            });

        $this->info("  → {$classified} ürün kategorize edildi, {$skipped} atlandı.");
    }

    /**
     * Ürün adı + markasını anahtar kelimelerle eşleştirip slug döndürür.
     * Eşleşme sayısına göre en yüksek puanlı kategori seçilir.
     */
    private function detectCategory(string $name, string $brand): ?string
    {
        $haystack = mb_strtolower("{$name} {$brand}", 'UTF-8');
        $scores = [];

        foreach (self::KEYWORD_RULES as $slug => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, mb_strtolower($keyword, 'UTF-8'))) {
                    // Uzun anahtar kelimeler daha fazla puan alır
                    $score += mb_strlen($keyword, 'UTF-8');
                }
            }
            if ($score > 0) {
                $scores[$slug] = $score;
            }
        }

        if (empty($scores)) {
            return null;
        }

        arsort($scores);

        return array_key_first($scores);
    }

    /* ── 3. Barkod üretimi ───────────────────────────────────────────── */

    private function generateBarcodes(): void
    {
        $this->info('[3/4] Barkodu olmayan ürünlere EAN-13 üretiliyor...');

        $count = 0;
        $chunk = (int) ($this->option('chunk') ?: 500);

        Product::query()
            ->where('tenant_id', $this->tenant->id)
            ->whereNull('barcode')
            ->orWhere('barcode', '')
            ->select(['id'])
            ->chunk($chunk, function ($products) use (&$count): void {
                foreach ($products as $product) {
                    $barcode = $this->generateEan13($product->id);
                    $count++;

                    if (! $this->dry) {
                        $product->update(['barcode' => $barcode]);
                    } else {
                        $this->line("  [DRY] ID:{$product->id} → {$barcode}");
                    }
                }
            });

        $this->info("  → {$count} ürüne EAN-13 barkod üretildi.");
    }

    /**
     * EAN-13 üretir.
     * Format: 869 (Türkiye GS1) + 1001 (şirket) + 5 haneli ürün kodu + 1 kontrol basamağı
     */
    private function generateEan13(int $productId): string
    {
        $prefix  = '8691001'; // 3 (TR) + 4 (şirket kodu)
        $item    = str_pad((string) ($productId % 100000), 5, '0', STR_PAD_LEFT);
        $partial = $prefix.$item;                      // 12 hane
        $check   = $this->ean13CheckDigit($partial);

        return $partial.$check;
    }

    private function ean13CheckDigit(string $partial12): int
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $partial12[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }

        return (10 - ($sum % 10)) % 10;
    }

    /* ── 4. SEO zenginleştirme (detaylı) ─────────────────────────────── */

    private const SITE_DOMAIN  = 'https://karacabeygrossmarket.com';

    private const SITE_NAME    = 'Karacabey Gross Market';

    private const SITE_LOCALE  = 'tr_TR';

    private const TWITTER_HANDLE = '@kgmarket';

    /**
     * 200 sabit yüksek niyetli (high-intent) Türkçe SEO anahtar kelimesi.
     * Her ürünün keyword listesine eklenir; ürün-özel keyword'lerle birleşip array_unique ile tekilleşir.
     * Marka / Konum / Servis / Fiyat / Kategori şemsiyesi / Niyet bazlı.
     */
    private const BASE_KEYWORDS = [
        // ── Marka (15) ──────────────────────────────────────────────
        'Karacabey Gross Market', 'KGM', 'KGM market', 'KGM Karacabey', 'Karacabey Gross',
        'karacabeygrossmarket', 'karacabey gross market online', 'Karacabey Gross Market sipariş',
        'Karacabey Gross Market kampanya', 'Karacabey Gross Market indirim', 'KGM online',
        'KGM sipariş', 'KGM market online', 'Karacabey marketi', 'Karacabey\'in marketi',

        // ── Konum: Karacabey (25) ──────────────────────────────────
        'Karacabey', 'Karacabey market', 'Karacabey online market', 'Karacabey online alışveriş',
        'Karacabey market siparişi', 'Karacabey hızlı teslimat', 'Karacabey kapıya teslim',
        'Karacabey online sipariş', 'Karacabey indirim', 'Karacabey kampanya',
        'Karacabey ucuz market', 'Karacabey en yakın market', 'Karacabey marketi online',
        'Karacabey\'de market', 'Karacabey\'de online sipariş', 'Karacabey\'de hızlı teslimat',
        'Karacabey\'de en yakın market', 'Karacabey market 7/24', 'Karacabey market kapıda',
        'Karacabey market satın al', 'Karacabey market fiyatları', 'Karacabey market online sipariş',
        'Karacabey ekonomik market', 'Karacabey hızlı market', 'Karacabey güvenilir market',

        // ── Konum: Bursa (20) ──────────────────────────────────────
        'Bursa', 'Bursa market', 'Bursa Karacabey market', 'Bursa online market',
        'Bursa market siparişi', 'Bursa hızlı teslimat', 'Bursa kapıya teslim',
        'Bursa Karacabey online market', 'Bursa Karacabey hızlı teslimat', 'Bursa marketleri',
        'Bursa online sipariş', 'Bursa ekonomik market', 'Bursa indirimli market',
        'Bursa Karacabey alışveriş', 'Bursa Karacabey kampanya', 'Bursa Karacabey market fiyatları',
        'Bursa marketi', 'Bursa market online', 'Bursa market kapıda', 'Bursa online alışveriş',

        // ── Servis: Teslimat (25) ──────────────────────────────────
        'hızlı teslimat', 'aynı gün teslimat', 'kapıya teslim', 'ücretsiz kargo', 'ücretsiz teslimat',
        '1 saatte teslimat', 'anlık teslimat', 'hemen teslimat', 'kapıya kadar', 'ev teslimatı',
        'işyerine teslimat', 'ofis teslimatı', 'kapıda ödeme', 'hızlı kargo', 'güvenli teslimat',
        'soğuk zincir teslimat', 'taze teslimat', 'günlük teslimat', 'aynı gün kapıda',
        'süratli teslimat', 'randevulu teslimat', 'zamanında teslimat', 'en hızlı market',
        'en hızlı teslimat', 'teslimat saatleri',

        // ── Servis: Online (25) ────────────────────────────────────
        'online market', 'online alışveriş', 'online sipariş', 'online market siparişi',
        'online market alışverişi', 'internetten market alışverişi', 'online süpermarket',
        'dijital market', 'mobil market', 'uygulamadan market', 'online market kampanyaları',
        'online ucuz market', 'online indirimli market', 'en iyi online market',
        'en uygun online market', 'en hızlı online market', 'internetten sipariş', 'internet marketi',
        'çevrimiçi market', 'online market siparişi ver', 'online ucuz alışveriş',
        'internet alışverişi', 'online market kategorileri', 'online market sepeti', 'mobil sipariş',

        // ── Servis: Fiyat / Avantaj (25) ───────────────────────────
        'ucuz market', 'ucuz online market', 'ekonomik market', 'uygun fiyat market', 'en ucuz market',
        'en uygun market', 'indirimli market', 'kampanyalı market', 'fırsat market', 'kampanya market',
        'büyük indirim market', 'fiyat avantajlı market', 'en uygun fiyat market',
        'fiyat karşılaştırma market', 'indirim market', 'ucuz alışveriş', 'ekonomik alışveriş',
        'uygun alışveriş', 'indirim alışveriş', 'kampanya alışveriş', 'dev indirim', 'süper fırsat',
        'mega indirim', 'haftalık fırsat', 'günün fırsatı',

        // ── Kategori Şemsiyesi (25) ────────────────────────────────
        'süpermarket', 'market', 'hipermarket', 'gross market', 'toptan market', 'perakende market',
        'semt marketi', 'mahalle marketi', 'kasap online', 'taze meyve sebze', 'et tavuk balık',
        'şarküteri kahvaltılık', 'süt ürünleri', 'temel gıda', 'atıştırmalık çikolata',
        'içecek market', 'dondurulmuş gıda', 'temizlik ürünleri', 'kişisel bakım ürünleri',
        'bebek ürünleri', 'evcil hayvan ürünleri', 'fırın pastane', 'kahvaltılık ürünler',
        'organik ürünler', 'doğal ürünler',

        // ── Genel Niyet (40) ───────────────────────────────────────
        'market alışverişi', 'haftalık market', 'aylık market alışverişi', 'günlük market alışverişi',
        'ihtiyaç alışverişi', 'günlük ihtiyaç', 'ev alışverişi', 'mutfak alışverişi', 'ev marketi',
        'mutfak marketi', 'gıda alışverişi', 'ev temel gıda', 'doğal market', 'yerel market',
        'yerli market', 'Türk markası', 'yerli ürünler', 'taze ürünler', 'günlük taze',
        'kaliteli market', 'güvenilir market', 'lider market', 'öncü market', 'büyük market zinciri',
        'yerel zincir market', 'mahalle marketi online', 'gelişmiş market', 'modern market',
        'premium market', 'herşey burada', 'tek tıkla market', 'anında market', 'ihtiyaca özel market',
        'bütçeye uygun market', 'her aileye uygun', 'her bütçeye uygun', 'her kese uygun market',
        'tam zamanında market', 'işine yarayan market', 'tüm ihtiyacın bir yerde',
    ];

    private function enrichSeo(): void
    {
        $this->info('[4/4] Ürün SEO alanları güncelleniyor (detaylı)...');

        $count = 0;
        $chunk = (int) ($this->option('chunk') ?: 500);

        $tenantId = $this->tenant->id;

        Product::query()
            ->where('tenant_id', $tenantId)
            ->with(['categories' => function ($q) use ($tenantId) {
                $q->where('categories.tenant_id', $tenantId)
                    ->where('categories.sort_order', '>', 0)
                    ->select('categories.id', 'categories.name', 'categories.slug');
            }])
            ->select([
                'id', 'name', 'slug', 'brand', 'description', 'barcode', 'sku',
                'price_cents', 'compare_at_price_cents', 'stock_quantity',
                'unit_name', 'image_url', 'cdn_image_url', 'seo',
                'created_at', 'updated_at',
            ])
            ->chunk($chunk, function ($products) use (&$count): void {
                foreach ($products as $product) {
                    $existing = is_array($product->seo) ? $product->seo : [];
                    $category = $product->categories->first();
                    $catName  = $category?->name ?? 'Gıda';
                    $catSlug  = $category?->slug;

                    $brand    = trim((string) ($product->brand ?? ''));
                    $hasBrand = $brand !== '' && strcasecmp($brand, self::SITE_NAME) !== 0;

                    $inStock  = $product->stock_quantity > 0;
                    $priceTl  = $product->price_cents > 0
                        ? number_format($product->price_cents / 100, 2, ',', '.')
                        : null;
                    $compareTl = $product->compare_at_price_cents
                        ? number_format($product->compare_at_price_cents / 100, 2, ',', '.')
                        : null;
                    $imageUrl  = $this->resolveImageUrl($product->cdn_image_url, $product->image_url);
                    $canonical = self::SITE_DOMAIN.'/product/'.$product->slug;

                    $title       = $this->buildTitle($product->name, $brand, $hasBrand);
                    $shortTitle  = $this->buildShortTitle($product->name, $brand, $hasBrand);
                    $description = $this->buildRichDescription(
                        $product->name, $brand, $catName, $inStock, $priceTl, $compareTl, $hasBrand, $product->unit_name,
                    );
                    $keywords = $this->buildRichKeywords($product->name, $brand, $catName, $catSlug, $hasBrand);

                    $schema = [
                        '@type'           => 'Product',
                        'name'            => $product->name,
                        'sku'             => $product->sku ?: (string) $product->id,
                        'mpn'             => (string) $product->id,
                        'gtin13'          => $product->barcode,
                        'brand'           => $hasBrand ? $brand : self::SITE_NAME,
                        'category'        => $catName,
                        'image'           => $imageUrl,
                        'description'     => $description,
                        'offers'          => [
                            '@type'           => 'Offer',
                            'price'           => $product->price_cents > 0 ? round($product->price_cents / 100, 2) : null,
                            'priceCurrency'   => 'TRY',
                            'priceValidUntil' => now()->addDays(14)->toDateString(),
                            'availability'    => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                            'itemCondition'   => 'https://schema.org/NewCondition',
                            'url'             => $canonical,
                            'seller'          => [
                                '@type' => 'Organization',
                                'name'  => self::SITE_NAME,
                            ],
                        ],
                    ];

                    $breadcrumbs = [
                        ['name' => 'Ana Sayfa', 'url' => self::SITE_DOMAIN],
                    ];
                    if ($category) {
                        $breadcrumbs[] = [
                            'name' => $category->name,
                            'url'  => self::SITE_DOMAIN.'/kategori/'.$category->slug,
                        ];
                    }
                    $breadcrumbs[] = ['name' => $product->name, 'url' => $canonical];

                    $newSeo = [
                        // Temel meta
                        'title'             => $title,
                        'short_title'       => $shortTitle,
                        'description'       => $description,
                        'keywords'          => $keywords,
                        'canonical'         => $canonical,
                        'robots'            => 'index, follow, max-image-preview:large, max-snippet:-1',

                        // Open Graph
                        'og_title'          => $title,
                        'og_description'    => $description,
                        'og_type'           => 'product',
                        'og_locale'         => self::SITE_LOCALE,
                        'og_url'            => $canonical,
                        'og_site_name'      => self::SITE_NAME,
                        'og_image'          => $imageUrl,
                        'og_image_alt'      => $product->name,

                        // Twitter Card
                        'twitter_card'        => $imageUrl ? 'summary_large_image' : 'summary',
                        'twitter_title'       => $title,
                        'twitter_description' => $description,
                        'twitter_image'       => $imageUrl,
                        'twitter_site'        => self::TWITTER_HANDLE,

                        // Yapısal veriler
                        'schema'              => $schema,
                        'breadcrumbs'         => $breadcrumbs,
                        'category_name'       => $catName,
                        'category_slug'       => $catSlug,
                        'brand'               => $hasBrand ? $brand : null,
                        'price_tl'            => $priceTl,
                        'compare_price_tl'    => $compareTl,
                        'in_stock'            => $inStock,
                        'availability'        => $inStock ? 'InStock' : 'OutOfStock',
                        'currency'            => 'TRY',
                        'language'            => 'tr',
                        'region'              => 'TR-16',

                        // Article meta
                        'published_time'      => optional($product->created_at)->toIso8601String(),
                        'modified_time'       => optional($product->updated_at)->toIso8601String(),
                    ];

                    // Erkur ve diğer ham alanları korumak için array_merge
                    $merged = array_merge($existing, $newSeo);

                    $count++;
                    if (! $this->dry) {
                        $product->update(['seo' => $merged]);
                    }
                }
            });

        $this->info("  → {$count} ürün SEO güncellendi (Open Graph + Twitter Card + schema.org + breadcrumb).");
    }

    private function buildTitle(string $name, string $brand, bool $hasBrand): string
    {
        $base = $hasBrand ? "{$name} — {$brand}" : $name;
        $full = "{$base} | ".self::SITE_NAME;

        return mb_strlen($full, 'UTF-8') > 70
            ? mb_substr($full, 0, 67, 'UTF-8').'...'
            : $full;
    }

    private function buildShortTitle(string $name, string $brand, bool $hasBrand): string
    {
        return $hasBrand ? "{$name} — {$brand}" : $name;
    }

    private function buildRichDescription(
        string $name,
        string $brand,
        string $category,
        bool $inStock,
        ?string $priceTl,
        ?string $comparePriceTl,
        bool $hasBrand,
        ?string $unitName,
    ): string {
        $brandPart = $hasBrand ? "{$brand} markalı " : '';
        $unit = $unitName ? " ({$unitName})" : '';
        $pricePart = '';
        if ($inStock && $priceTl !== null) {
            $pricePart = $comparePriceTl
                ? " Şimdi ₺{$priceTl} (liste fiyatı ₺{$comparePriceTl})."
                : " Şimdi ₺{$priceTl}.";
        }
        $stockPart = $inStock
            ? 'Stokta var.'
            : 'Yakında tekrar stokta.';
        $cta = $inStock
            ? 'Karacabey Gross Market üzerinden online sipariş ver, hızlı teslimat ile kapına gelsin.'
            : 'Stoğa eklenince ilk sen haberdar ol — Karacabey Gross Market\'i takip et.';

        $desc = "{$name}{$unit} — {$brandPart}{$category} kategorisi. {$stockPart}{$pricePart} {$cta}";

        return mb_strlen($desc, 'UTF-8') > 320
            ? mb_substr($desc, 0, 317, 'UTF-8').'...'
            : $desc;
    }

    /** @return string[] */
    private function buildRichKeywords(
        string $name,
        string $brand,
        string $category,
        ?string $categorySlug,
        bool $hasBrand,
    ): array {
        // Ürün-özel keyword'ler (ön planda olur, daha alakalı)
        $specific = [
            $name,
            "{$name} fiyat",
            "{$name} fiyatları",
            "{$name} satın al",
            "{$name} sipariş",
            "{$name} sipariş ver",
            "{$name} online",
            "{$name} kampanya",
            "{$name} indirim",
            "{$name} en uygun fiyat",
            "{$name} Karacabey",
            "{$name} Bursa",
            $category,
            "{$category} fiyatları",
            "{$category} kampanyaları",
            "{$category} online sipariş",
            "{$category} sipariş",
            "{$category} Karacabey",
        ];

        if ($hasBrand) {
            array_unshift(
                $specific,
                $brand,
                "{$brand} {$name}",
                "{$brand} fiyat",
                "{$brand} sipariş",
                "{$brand} online",
                "{$brand} Karacabey",
            );
        }

        if ($categorySlug) {
            $specific[] = "{$categorySlug} ürünleri";
        }

        // Ürün-özel önce, 200 sabit base sonra; dedupe (ürün-özel önceliği korur)
        return array_values(array_unique(array_merge($specific, self::BASE_KEYWORDS)));
    }

    private function resolveImageUrl(?string $cdn, ?string $image): ?string
    {
        $candidate = $cdn ?: $image;
        if (! $candidate) {
            return null;
        }

        if (str_starts_with($candidate, 'http://') || str_starts_with($candidate, 'https://')) {
            return $candidate;
        }

        return self::SITE_DOMAIN.'/'.ltrim($candidate, '/');
    }
}
