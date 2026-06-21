<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Tenant;
use App\Support\ProductBrandInferer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NormalizeMobileCatalog extends Command
{
    protected $signature = 'kgm:normalize-mobile-catalog
        {--tenant-id= : Tenant ID}
        {--dry-run : Raporla, yazma}
        {--chunk=500 : Ürün chunk boyutu}';

    protected $description = 'Mobil katalog için kategori adlarını sadeleştirir, teknik POS kategorilerini gizler ve eksik markaları doldurur.';

    private const CATEGORIES = [
        'meyve-sebze' => ['Meyve & Sebze', 10, 'Günlük taze meyve, sebze ve yeşillikler.'],
        'et-tavuk-sarkuteri' => ['Et, Tavuk & Şarküteri', 20, 'Et, tavuk, sucuk, salam ve şarküteri ürünleri.'],
        'sut-kahvaltilik' => ['Süt Ürünleri & Kahvaltılık', 30, 'Süt, yoğurt, peynir, zeytin, reçel ve kahvaltılık ürünler.'],
        'firin-pastane' => ['Fırın & Pastane', 40, 'Ekmek, yufka, pastane ve pratik fırın ürünleri.'],
        'temel-gida' => ['Temel Gıda', 50, 'Bakliyat, yağ, şeker, un, baharat ve mutfak stok ürünleri.'],
        'atistirmalik' => ['Atıştırmalık & Çikolata', 60, 'Cips, kuruyemiş, şekerleme, çikolata ve tatlı ürünleri.'],
        'icecek' => ['İçecekler', 70, 'Su, soda, meyve suyu, gazlı içecek, çay ve kahve.'],
        'donmus-hazir' => ['Dondurulmuş & Hazır Gıda', 80, 'Dondurma, pizza, hazır yemek ve pratik gıdalar.'],
        'temizlik' => ['Temizlik', 90, 'Ev temizliği, kağıt ürünleri, deterjan ve hijyen ürünleri.'],
        'kisisel-bakim' => ['Kişisel Bakım', 100, 'Şampuan, diş bakım, sabun, deodorant ve bakım ürünleri.'],
        'bebek' => ['Bebek', 110, 'Bebek maması, bez, ıslak mendil ve bebek bakım ürünleri.'],
        'ev-yasam' => ['Ev & Yaşam', 120, 'Mutfak, ev gereçleri ve günlük yaşam ürünleri.'],
        'diger' => ['Diğer Ürünler', 999, 'Diğer market ürünleri.'],
    ];

    private const RULES = [
        'meyve-sebze' => ['meyve', 'sebze', 'domates', 'salatalık', 'biber', 'patates', 'soğan', 'limon', 'elma', 'muz', 'mandalina', 'marul', 'maydanoz', 'roka'],
        'et-tavuk-sarkuteri' => ['dana', 'kuzu', 'tavuk', 'piliç', 'sucuk', 'pastırma', 'salam', 'sosis', 'şarküteri', 'et ', 'kıyma', 'kanat'],
        'sut-kahvaltilik' => ['süt', 'yoğurt', 'ayran', 'peynir', 'peynr', 'peyn', 'kaşar', 'zeytin', 'yumurta', 'reçel', 'marmelat', 'bal', 'tereyağ', 'margarin', 'kaymak', 'kahvaltı'],
        'firin-pastane' => ['ekmek', 'yufka', 'lavaş', 'bazlama', 'pide', 'simit', 'pastane', 'kadayıf', 'baklava', 'kurabiye', 'kemalpaşa'],
        'temel-gida' => ['pirinç', 'bulgur', 'bakliyat', 'mercimek', 'nohut', 'fasulye', 'un ', 'şeker', 'pudra şekeri', 'nişasta', 'tuz', 'yağ', 'zeytinyağ', 'ayçiçek', 'baharat', 'bulyon', 'kabartma', 'maya', 'makarna', 'salça', 'ketçap', 'mayonez', 'sos', 'turşu', 'mahlep', 'buğday', 'konserve', 'bezelye', 'pilaki', 'sarma yaprak', 'çorba', 'hazır yemek', 'haşhaş'],
        'atistirmalik' => ['cips', 'lays', 'doritos', 'ruffles', 'fritolay', 'pringles', 'çerezza', 'cerezza', 'soslu', 'kuruyemiş', 'fındık', 'fıstık', 'badem', 'çikolata', 'cikolata', 'çiko', 'ciko', 'gofret', 'gof', 'bisküvi', 'biskuvi', 'kraker', 'şekerleme', 'sakız', 'vivident', 'first', 'olips', 'lokum', 'puding', 'jole', 'kakao', 'krem şanti', 'cookie', 'topkek', 'tofit', 'kek', 'karamel', 'helva'],
        'icecek' => ['su ', 'sular', 'kola', 'cola', 'gazoz', 'soda', 'm suyu', 'meyve suyu', 'mey suyu', 'nektar', 'içecek', 'drink', 'fuse', 'fusetea', 'kızılay', 'uludağ', 'boza', 'çay', 'adaçayı', 'kuşburnu', 'kahve', 'nescafe', 'toz içecek', 'aroma', 'limonata', 'energy'],
        'donmus-hazir' => ['dondurma', 'golf', 'pizza', 'superfresh', 'perfetto', 'donmuş', 'dondurulmuş', 'hazır gıda'],
        'temizlik' => ['tuvalet kağıdı', 'havlu', 'peçete', 'mendil', 'deterjan', 'detrjan', 'detrjani', 'temizleyici', 'temizlik', 'çamaşır', 'bulaşık', 'bulşk', 'çöp', 'sünger', 'paspas', 'faraş', 'tem bezi', 'temizlik bezi', 'eldiven', 'yumuşatıcı', 'yum ', 'wc', 'porçöz', 'cif', 'finish', 'omo', 'peros', 'bingo', 'ernet', 'tex', 'naftalin'],
        'kisisel-bakim' => ['şampuan', 'şamp', 'samp', 'saç kremi', 'bakım krem', 'diş macunu', 'diş mac', 'dis mac', 'diş fırçası', 'dis fir', 'ağız', 'agiz', 'listerine', 'sabun', 'sıvı sabun', 'duş', 'dus', 'palmolive', 'roll', 'rolon', 'deo', 'deodorant', 'stick', 'tıraş', 'tiras', 'güneş kremi', 'gunes kremi', 'tüy dökücü', 'ped', 'kadın pedi', 'kolonya', 'krem ', 'maske', 'ağda', 'agda', 'neutrogen', 'nivea', 'rexona', 'arko', 'veet', 'colgate', 'ipana', 'garnier'],
        'bebek' => ['mama', 'bebek', 'bebek bezi', 'kaşık maması', 'toz mama', 'devam sütü', 'bebelac', 'aptamil', 'prima', 'molfix', 'önlem', 'onlem', 'dalin', 'johnsons baby'],
        'ev-yasam' => ['kaşık', 'tabak', 'bardak', 'sürahi', 'surahi', 'maşrapa', 'masrapa', 'tuzluk', 'tepsi', 'çerezlik', 'cerezlik', 'kaşıklık', 'kasiklik', 'kavanoz', 'küllük', 'kulluk', 'gırgır', 'girgir', 'süzgeç', 'suzgec', 'çatal', 'bıçak', 'folyo', 'streç', 'poşet', 'buzdolabı poşeti', 'pişirme kağıdı', 'pisirme kagidi', 'saklama kabı', 'sufle kabı', 'kesme tahtası', 'kömür', 'mangal', 'soyacak', 'çırpıcı', 'cirpici', 'örtü', 'ortu', 'sap ', 'fırça sapı', 'tel kartela', 'cihaz', 'pil', 'ampul', 'mum'],
    ];

    public function handle(): int
    {
        $tenant = $this->resolveTenant();
        if (! $tenant) {
            $this->error('Tenant bulunamadı.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $chunk = max(100, (int) $this->option('chunk'));

        if ($dry) {
            $this->warn('DRY-RUN: değişiklik yazılmayacak.');
        }

        DB::transaction(function () use ($tenant, $dry, $chunk): void {
            $categoryIds = $this->upsertCategories($tenant->id, $dry);
            $brandCount = $this->fillMissingBrands($tenant->id, $dry);
            $classified = $this->classifyProducts($tenant->id, $categoryIds, $chunk, $dry);
            $hidden = $this->hideLegacyCategories($tenant->id, array_keys(self::CATEGORIES), $dry);
            $emptyHidden = $this->hideEmptyMobileCategories($tenant->id, $dry);

            $this->info("Marka doldurulan ürün: {$brandCount}");
            $this->info("Temiz kategoriye bağlanan ürün: {$classified}");
            $this->info("Pasife alınan eski/teknik kategori: {$hidden}");
            $this->info("Ürünsüz mobil kategori pasife alındı: {$emptyHidden}");
        });

        if (! $dry) {
            Cache::flush();
        }

        $this->info('Mobil katalog kategorileri temizlendi.');

        return self::SUCCESS;
    }

    private function resolveTenant(): ?Tenant
    {
        $tenantId = $this->option('tenant-id');

        return $tenantId
            ? Tenant::query()->find((int) $tenantId)
            : Tenant::query()->first();
    }

    /** @return array<string, int> */
    private function upsertCategories(int $tenantId, bool $dry): array
    {
        $ids = [];

        foreach (self::CATEGORIES as $slug => [$name, $sortOrder, $description]) {
            if (! $dry) {
                $category = Category::query()->updateOrCreate(
                    ['tenant_id' => $tenantId, 'slug' => $slug],
                    [
                        'parent_id' => null,
                        'name' => $name,
                        'description' => $description,
                        'sort_order' => $sortOrder,
                        'is_active' => true,
                        'seo' => [
                            'title' => "{$name} | Karacabey Gross Market",
                            'description' => $description,
                        ],
                    ],
                );

                $ids[$slug] = (int) $category->id;
                continue;
            }

            $ids[$slug] = (int) (Category::query()
                ->where('tenant_id', $tenantId)
                ->where('slug', $slug)
                ->value('id') ?? 0);
        }

        return $ids;
    }

    private function fillMissingBrands(int $tenantId, bool $dry): int
    {
        $inferer = new ProductBrandInferer;
        $count = 0;
        $hasSearchKeywords = Schema::hasColumn('products', 'search_keywords');

        DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where(function ($query): void {
                $query->whereNull('brand')->orWhere('brand', '');
            })
            ->orderBy('id')
            ->select(['id', 'name', 'barcode', 'sku'])
            ->chunkById(500, function ($products) use ($inferer, $tenantId, $dry, $hasSearchKeywords, &$count): void {
                foreach ($products as $product) {
                    $brand = $inferer->infer($product->name) ?? 'Karacabey Gross Market';
                    $count++;

                    if ($dry) {
                        continue;
                    }

                    $payload = [
                        'brand' => $brand,
                        'updated_at' => now(),
                    ];

                    if ($hasSearchKeywords) {
                        $payload['search_keywords'] = trim(implode(' ', array_filter([
                            $product->name,
                            $brand,
                            $product->barcode ?? null,
                            $product->sku ?? null,
                        ])));
                    }

                    DB::table('products')
                        ->where('tenant_id', $tenantId)
                        ->where('id', $product->id)
                        ->update($payload);
                }
            }, 'id');

        return $count;
    }

    /** @param array<string, int> $categoryIds */
    private function classifyProducts(int $tenantId, array $categoryIds, int $chunk, bool $dry): int
    {
        $classified = 0;

        DB::table('products')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('id')
            ->select(['id', 'name', 'brand'])
            ->chunkById($chunk, function ($products) use ($tenantId, $categoryIds, $dry, &$classified): void {
                $pairs = [];
                $productIds = [];

                foreach ($products as $product) {
                    $slug = $this->detectCategory((string) $product->name, (string) $product->brand);
                    $categoryId = $categoryIds[$slug] ?? $categoryIds['diger'] ?? null;
                    if (! $categoryId) {
                        continue;
                    }

                    $productIds[] = (int) $product->id;
                    $pairs[] = [
                        'product_id' => (int) $product->id,
                        'category_id' => $categoryId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $classified += count($pairs);

                if ($dry || empty($pairs)) {
                    return;
                }

                DB::table('category_product')->whereIn('product_id', $productIds)->delete();
                DB::table('category_product')->insertOrIgnore($pairs);
            }, 'id');

        return $classified;
    }

    private function hideLegacyCategories(int $tenantId, array $canonicalSlugs, bool $dry): int
    {
        $query = DB::table('categories')
            ->where('tenant_id', $tenantId)
            ->whereNotIn('slug', $canonicalSlugs)
            ->where('is_active', true);

        $count = (clone $query)->count();

        if (! $dry) {
            $query->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
        }

        return $count;
    }

    private function hideEmptyMobileCategories(int $tenantId, bool $dry): int
    {
        $ids = DB::table('categories')
            ->where('tenant_id', $tenantId)
            ->whereIn('slug', array_keys(self::CATEGORIES))
            ->whereNotIn('slug', ['diger'])
            ->where('is_active', true)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('category_product')
                    ->whereColumn('category_product.category_id', 'categories.id');
            })
            ->pluck('id');

        if (! $dry && $ids->isNotEmpty()) {
            DB::table('categories')->whereIn('id', $ids)->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
        }

        return $ids->count();
    }

    private function detectCategory(string $name, string $brand): string
    {
        $haystack = $this->normalize("{$name} {$brand}");
        $scores = [];

        foreach (self::RULES as $slug => $keywords) {
            foreach ($keywords as $keyword) {
                $needle = $this->normalize($keyword);
                if ($needle !== '' && $this->matchesKeyword($haystack, $needle)) {
                    $scores[$slug] = ($scores[$slug] ?? 0) + mb_strlen($needle, 'UTF-8');
                }
            }
        }

        if (empty($scores)) {
            return 'diger';
        }

        arsort($scores);

        return (string) array_key_first($scores);
    }

    private function matchesKeyword(string $haystack, string $needle): bool
    {
        if (mb_strlen($needle, 'UTF-8') <= 2) {
            return (bool) preg_match('/(^|\s)'.preg_quote($needle, '/').'($|\s)/u', $haystack);
        }

        return str_contains($haystack, $needle);
    }

    private function normalize(string $value): string
    {
        $value = Str::ascii(Str::squish($value));
        $value = Str::lower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;

        return trim($value);
    }
}
