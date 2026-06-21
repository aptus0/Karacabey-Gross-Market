<?php

namespace App\Services\Cargo;

use App\Models\CargoProviderSetting;
use App\Services\Cargo\Contracts\CargoProvider;
use App\Services\Cargo\Providers\ArasCargoProvider;
use App\Services\Cargo\Providers\MngCargoProvider;
use App\Services\Cargo\Providers\PttCargoProvider;
use App\Services\Cargo\Providers\YurticiCargoProvider;
use App\Support\TenantResolver;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class CargoManager
{
    public function resolve(string $carrier, array $credentials = []): CargoProvider
    {
        return match (strtoupper($carrier)) {
            'YURTICI' => new YurticiCargoProvider($credentials),
            'ARAS'    => new ArasCargoProvider($credentials),
            'PTT'     => new PttCargoProvider($credentials),
            'MNG'     => new MngCargoProvider($credentials),
            default   => throw new InvalidArgumentException("Desteklenmeyen kargo sağlayıcısı: {$carrier}"),
        };
    }

    /**
     * DB ayarlarından sağlayıcıyı yükle (kimlik bilgileriyle birlikte).
     */
    public function resolveFromSettings(string $carrier, int $tenantId): CargoProvider
    {
        $setting = CargoProviderSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('code', strtoupper($carrier))
            ->where('is_active', true)
            ->first();

        if (! $setting) {
            throw new RuntimeException("'{$carrier}' kargo sağlayıcısı bu tenant için aktif değil.");
        }

        if (! $setting->isConfigured()) {
            throw new RuntimeException("'{$carrier}' kargo sağlayıcısının API kimlik bilgileri eksik.");
        }

        return $this->resolve($carrier, $setting->credentials ?? []);
    }

    /**
     * Tenant için aktif kargo seçeneklerini döndür.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, CargoProviderSetting>
     */
    public function activeOptions(int $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return CargoProviderSetting::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('price_cents')
            ->get()
            ->filter(fn (CargoProviderSetting $setting): bool => $setting->isConfigured())
            ->values();
    }
}
