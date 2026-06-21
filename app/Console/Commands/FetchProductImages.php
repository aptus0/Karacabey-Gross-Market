<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FetchProductImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:fetch-images {--limit=100} {--force : Görseli olanları da güncelle}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ürün isimlerine göre internetten görsel çeker ve günceller.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = $this->option('limit');
        $force = $this->option('force');

        $query = Product::query();

        if (!$force) {
            $query->whereNull('image_url')->orWhere('image_url', '');
        }

        $products = $query->take($limit)->get();

        if ($products->isEmpty()) {
            $this->info('Güncellenecek ürün bulunamadı.');
            return;
        }

        $this->info($products->count() . ' ürün için görsel aranıyor...');

        $bar = $this->output->createProgressBar($products->count());
        $bar->start();

        foreach ($products as $product) {
            $imageUrl = $this->searchImage($product->name);

            if ($imageUrl) {
                $product->update(['image_url' => $imageUrl]);
            }

            $bar->advance();
            // API limitlerine takılmamak için kısa bir bekleme
            usleep(500000); 
        }

        $bar->finish();
        $this->newLine();
        $this->info('İşlem tamamlandı.');
    }

    /**
     * DuckDuckGo veya benzeri bir servis üzerinden görsel arama simülasyonu / API çağrısı
     * Not: Gerçek projede Google Custom Search veya Bing Search API anahtarı gereklidir.
     * Bu örnekte placeholder veya basit bir arama mantığı kurulabilir.
     */
    private function searchImage($name)
    {
        try {
            // Örnek: Unsplash veya Pixabay API kullanılabilir. 
            // Burada kullanıcıya özel bir çözüm olarak Google Search API entegrasyonu önerilir.
            // Şimdilik mantığı göstermek adına bir API endpoint simüle ediyoruz.
            
            $searchQuery = urlencode($name . " market ürünü");
            
            // Alternatif: Doggo.pm veya benzeri hızlı görsel servisleri
            // Gerçek dünyada: return $this->googleSearch($name);
            
            // Basit bir Google Search API (Custom Search) örneği varsayalım:
            // $apiKey = config('services.google.search_key');
            // $cx = config('services.google.search_cx');
            // $response = Http::get("https://www.googleapis.com/customsearch/v1", [
            //     'key' => $apiKey,
            //     'cx' => $cx,
            //     'q' => $name,
            //     'searchType' => 'image',
            //     'num' => 1
            // ]);
            // return $response->json('items.0.link');

            // Şimdilik test için LoremFlickr veya Unsplash Source kullanalım (ürün ismiyle eşleşen)
            return "https://loremflickr.com/800/800/" . Str::slug($name);

        } catch (\Exception $e) {
            return null;
        }
    }
}
