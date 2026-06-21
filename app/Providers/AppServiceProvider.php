<?php

namespace App\Providers;

use App\Models\ApiToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::viaRequest('api-token', function (Request $request) {
            $plainToken = $request->bearerToken();

            if (! $plainToken) {
                return null;
            }

            $token = ApiToken::query()
                ->where('token_hash', hash('sha256', $plainToken))
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->with('user')
                ->first();

            if (! $token) {
                return null;
            }

            $token->forceFill(['last_used_at' => now()])->save();
            $request->attributes->set('api_token', $token);

            return $token->user;
        });

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(300)->by($this->rateLimiterKey($request, 'api')));
        RateLimiter::for('payments', fn (Request $request) => Limit::perMinute(20)->by($this->rateLimiterKey($request, 'payments')));
        RateLimiter::for('payment-callback', fn (Request $request) => Limit::perMinute(120)->by('payment-callback|'.$request->ip()));
    }

    private function rateLimiterKey(Request $request, string $bucket): string
    {
        if ($request->user()?->id) {
            return $bucket.'|user|'.$request->user()->id;
        }

        if ($request->bearerToken()) {
            return $bucket.'|token|'.substr(hash('sha256', $request->bearerToken()), 0, 24);
        }

        $cartToken = $request->header('X-Cart-Token') ?: $request->input('cart_token');

        if ($cartToken) {
            return $bucket.'|cart|'.substr(hash('sha256', (string) $cartToken), 0, 24);
        }

        $checkoutKey = $request->header('X-Checkout-Key') ?: $request->input('checkout_key');

        if ($checkoutKey) {
            return $bucket.'|checkout|'.substr(hash('sha256', (string) $checkoutKey), 0, 24);
        }

        $phone = preg_replace('/\D+/', '', (string) $request->input('phone', '')) ?: null;

        if ($phone) {
            return $bucket.'|phone|'.$phone;
        }

        $email = mb_strtolower(trim((string) ($request->input('customer.email') ?: $request->input('email') ?: '')));

        if ($email !== '') {
            return $bucket.'|email|'.substr(hash('sha256', $email), 0, 24);
        }

        return $bucket.'|ip|'.$request->ip();
    }
}
