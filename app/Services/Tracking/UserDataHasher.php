<?php

namespace App\Services\Tracking;

/**
 * Meta CAPI ve TikTok Events API SHA-256 hash'lenmiş PII bekler.
 * GA4 Measurement Protocol için hashleme şart değildir ama önerilir.
 */
final class UserDataHasher
{
    public static function email(?string $email): ?string
    {
        if (! $email) return null;
        return hash('sha256', strtolower(trim($email)));
    }

    public static function phone(?string $phone): ?string
    {
        if (! $phone) return null;
        // E.164 normalize (sadece rakam)
        $normalized = preg_replace('/\D+/', '', $phone);
        if (! $normalized) return null;
        return hash('sha256', $normalized);
    }

    public static function name(?string $name): ?string
    {
        if (! $name) return null;
        return hash('sha256', strtolower(trim($name)));
    }

    public static function city(?string $city): ?string
    {
        if (! $city) return null;
        return hash('sha256', strtolower(preg_replace('/\s+/', '', $city)));
    }

    public static function country(?string $country = 'tr'): string
    {
        return hash('sha256', strtolower(trim($country)));
    }
}
