<?php

namespace App\Support;

class CdnUrl
{
    public static function for(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $cdnBase = rtrim((string) config('commerce.domains.cdn', ''), '/');
        if ($cdnBase === '') {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return $cdnBase.$url;
        }

        $parsed = parse_url($url);
        if (! is_array($parsed) || empty($parsed['path'])) {
            return $url;
        }

        $path = (string) $parsed['path'];
        $isStorageOrAsset = str_starts_with($path, '/storage/')
            || str_starts_with($path, '/assets/')
            || str_starts_with($path, '/uploads/');

        if (! $isStorageOrAsset) {
            return $url;
        }

        $query = isset($parsed['query']) && $parsed['query'] !== '' ? '?'.$parsed['query'] : '';

        return $cdnBase.$path.$query;
    }
}

