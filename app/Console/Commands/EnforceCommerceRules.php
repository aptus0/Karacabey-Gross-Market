<?php

namespace App\Console\Commands;

use App\Models\CargoProviderSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EnforceCommerceRules extends Command
{
    protected $signature = 'kgm:enforce-commerce-rules';

    protected $description = 'Sıfır fiyatlı ürünleri ve eksik kimlik bilgili kargo sağlayıcılarını pasife alır.';

    public function handle(): int
    {
        $products = DB::table('products')
            ->where('price_cents', '<=', 0)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        $cargo = 0;
        CargoProviderSetting::query()
            ->where('is_active', true)
            ->get()
            ->each(function (CargoProviderSetting $setting) use (&$cargo): void {
                if ($setting->isConfigured()) {
                    return;
                }

                $setting->update(['is_active' => false]);
                $cargo++;
            });

        if ($products > 0 || $cargo > 0) {
            Cache::flush();
        }

        $this->info("Pasife alınan ürün: {$products}; kargo sağlayıcı: {$cargo}");

        return self::SUCCESS;
    }
}
