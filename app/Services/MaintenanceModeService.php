<?php

namespace App\Services;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class MaintenanceModeService
{
    public const SETTINGS_KEY = 'maintenance';

    /**
     * Build a safe public maintenance state from tenant settings.
     * The setting lives in tenants.settings["maintenance"], so the panel can
     * switch web/mobile/API maintenance without requiring Laravel's global down mode.
     */
    public function status(Tenant $tenant): array
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $maintenance = Arr::get($settings, self::SETTINGS_KEY, []);
        $maintenance = is_array($maintenance) ? $maintenance : [];

        $enabled = (bool) ($maintenance['enabled'] ?? false);
        $startsAt = $this->parseDate($maintenance['starts_at'] ?? null);
        $endsAt = $this->parseDate($maintenance['ends_at'] ?? null);
        $now = CarbonImmutable::now();
        $insideWindow = (! $startsAt || $startsAt->lessThanOrEqualTo($now))
            && (! $endsAt || $endsAt->greaterThan($now));
        $active = $enabled && $insideWindow;

        $channels = [
            'storefront' => (bool) ($maintenance['storefront'] ?? true),
            'checkout' => (bool) ($maintenance['checkout'] ?? true),
            'api_writes' => (bool) ($maintenance['api_writes'] ?? true),
            'mobile' => (bool) ($maintenance['mobile'] ?? false),
        ];

        return [
            'enabled' => $enabled,
            'active' => $active,
            'title' => $this->cleanText($maintenance['title'] ?? null, 'Kısa bir bakım yapıyoruz'),
            'message' => $this->cleanText(
                $maintenance['message'] ?? null,
                'Karacabey Gross Market deneyimini daha hızlı ve güvenli hale getirmek için kısa süreli bakımdayız.'
            ),
            'starts_at' => $startsAt?->toIso8601String(),
            'ends_at' => $endsAt?->toIso8601String(),
            'updated_at' => isset($maintenance['updated_at']) ? (string) $maintenance['updated_at'] : null,
            'updated_by' => isset($maintenance['updated_by']) ? (string) $maintenance['updated_by'] : null,
            'channels' => $channels,
            'support' => [
                'phone' => '(0224) 676 84 33',
                'whatsapp' => '9065453458663',
                'email' => 'support@karacabeygrossmarket.com',
            ],
        ];
    }

    public function update(Tenant $tenant, array $data, ?string $updatedBy = null): array
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        $settings[self::SETTINGS_KEY] = [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'storefront' => (bool) ($data['storefront'] ?? true),
            'checkout' => (bool) ($data['checkout'] ?? true),
            'api_writes' => (bool) ($data['api_writes'] ?? true),
            'mobile' => (bool) ($data['mobile'] ?? false),
            'title' => $this->cleanText($data['title'] ?? null, 'Kısa bir bakım yapıyoruz'),
            'message' => $this->cleanText($data['message'] ?? null, 'Karacabey Gross Market deneyimini iyileştiriyoruz.'),
            'starts_at' => $this->normalizeDateInput($data['starts_at'] ?? null),
            'ends_at' => $this->normalizeDateInput($data['ends_at'] ?? null),
            'updated_at' => now()->toIso8601String(),
            'updated_by' => $updatedBy,
        ];

        $tenant->forceFill(['settings' => $settings])->save();

        return $this->status($tenant->refresh());
    }

    public function shouldBlockStorefront(Tenant $tenant): bool
    {
        $status = $this->status($tenant);

        return (bool) ($status['active'] && $status['channels']['storefront']);
    }

    public function shouldBlockApiWrite(Tenant $tenant): bool
    {
        $status = $this->status($tenant);

        return (bool) ($status['active'] && $status['channels']['api_writes']);
    }

    public function shouldBlockCheckout(Tenant $tenant): bool
    {
        $status = $this->status($tenant);

        return (bool) ($status['active'] && $status['channels']['checkout']);
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeDateInput(mixed $value): ?string
    {
        $date = $this->parseDate($value);

        return $date?->toDateTimeString();
    }

    private function cleanText(mixed $value, string $fallback): string
    {
        $text = trim((string) ($value ?? ''));
        $text = preg_replace('/\s+/', ' ', $text) ?: '';

        return $text !== '' ? mb_substr($text, 0, 500) : $fallback;
    }
}
