<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PerformanceHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            $this->applyGetHeaders($request, $response);
        } else {
            $this->applyWriteHeaders($request, $response);
        }

        return $response;
    }

    private function applyGetHeaders(Request $request, Response $response): void
    {
        if (! $request->is('api/v1*')) {
            return;
        }

        if ($this->isPrivateApiPath($request)) {
            $this->noStore($response);

            return;
        }

        if (! $this->isPublicCacheableApiPath($request)) {
            return;
        }

        if ($response->headers->has('Cache-Control') && str_contains((string) $response->headers->get('Cache-Control'), 'no-store')) {
            return;
        }

        $maxAge = (int) config('web_performance.http.public_api_max_age', 60);
        $sMaxAge = (int) config('web_performance.http.public_api_s_maxage', 300);
        $stale = (int) config('web_performance.http.public_api_stale_while_revalidate', 86400);

        $response->headers->set(
            'Cache-Control',
            sprintf('public, max-age=%d, s-maxage=%d, stale-while-revalidate=%d', $maxAge, $sMaxAge, $stale)
        );
        $response->headers->set('Vary', trim($response->headers->get('Vary') . ', Accept, Origin', ', '));
        $response->headers->set('X-KGM-Cache-Profile', 'public-api');
    }

    private function applyWriteHeaders(Request $request, Response $response): void
    {
        if (! $request->is('api/v1*')) {
            return;
        }

        if ((bool) config('web_performance.http.private_no_store', true)) {
            $this->noStore($response);
        }
    }

    private function noStore(Response $response): void
    {
        $response->headers->set('Cache-Control', 'no-store, no-cache, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('X-KGM-Cache-Profile', 'private');
    }

    private function isPrivateApiPath(Request $request): bool
    {
        return $request->is('api/v1/auth*')
            || $request->is('api/v1/cart*')
            || $request->is('api/v1/checkout*')
            || $request->is('api/v1/orders*')
            || $request->is('api/v1/notifications*')
            || $request->is('api/v1/favorites*')
            || $request->is('api/v1/addresses*')
            || $request->is('api/v1/payments*')
            || $request->is('api/v1/support*');
    }

    private function isPublicCacheableApiPath(Request $request): bool
    {
        return $request->is('api/v1/products')
            || $request->is('api/v1/products/*')
            || $request->is('api/v1/categories')
            || $request->is('api/v1/categories/*')
            || $request->is('api/v1/content*')
            || $request->is('api/v1/cargo/options')
            || $request->is('api/v1/system/status');
    }
}
