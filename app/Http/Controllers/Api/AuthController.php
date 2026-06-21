<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\CartCoupon;
use App\Models\CartItem;
use App\Models\User;
use App\Services\Auth\ApiTokenIssuer;
use App\Services\Auth\OAuthProviderManager;
use App\Services\PushNotificationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS   = 5;
    private const DECAY_SECONDS  = 900; // 15 dakika

    public function register(
        Request $request,
        ApiTokenIssuer $tokenIssuer,
        PushNotificationService $pushNotifications,
    ): JsonResponse
    {
        // Telefonu önceden normalize ederek doğru unique kontrolü sağla
        $request->merge([
            'phone' => $this->normalizePhone((string) $request->input('phone', '')),
        ]);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'phone'       => ['required', 'string', 'regex:/^5[0-9]{9}$/', 'unique:users,phone'],
            'password'    => [
                'required',
                'string',
                Password::min(8)->max(255),
            ],
            'location'    => ['nullable', 'string', 'max:160'],
            'device_name' => ['nullable', 'string', 'max:80'],
            'cart_token'  => ['nullable', 'string', 'max:64'],
        ], [
            'phone.regex'    => 'Geçerli bir Türkiye telefon numarası girin (5xx xxx xx xx).',
            'phone.unique'   => 'Bu telefon numarasıyla zaten bir hesap mevcut.',
            'password.min'   => 'Şifreniz en az 8 karakter olmalı.',
        ]);

        $user = User::query()->create([
            'name'          => trim($validated['name']),
            'phone'         => $validated['phone'],
            'password'      => $validated['password'],
            'last_ip'       => $request->ip(),
            'last_location' => $validated['location'] ?? null,
            'last_login_at' => now(),
        ]);

        $this->claimGuestCart($request, $user);
        $pushNotifications->sendToUser(
            $user,
            'Karacabey Gross Market hesabınız hazır',
            'Yeni kampanyalar, ürün fırsatları ve sipariş güncellemeleri için bildirim merkezi aktif edildi.',
            [
                'type' => 'general',
                'action_url' => '/notifications',
                'payload' => [
                    'source' => 'welcome',
                ],
            ],
        );

        return response()->json($this->buildAuthPayload(
            $user,
            $tokenIssuer->issue($user, (string) $request->input('device_name', 'default')),
        ), 201);
    }

    public function login(Request $request, ApiTokenIssuer $tokenIssuer): JsonResponse
    {
        $credentials = $request->validate([
            'phone'       => ['required', 'string', 'max:20'],
            'password'    => ['required', 'string'],
            'location'    => ['nullable', 'string', 'max:160'],
            'device_name' => ['nullable', 'string', 'max:80'],
            'cart_token'  => ['nullable', 'string', 'max:64'],
        ]);

        $phone        = $this->normalizePhone($credentials['phone']);
        $throttleKey  = $this->throttleKey($phone, $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $retryAfter = RateLimiter::availableIn($throttleKey);
            $minutes    = (int) ceil($retryAfter / 60);

            throw new HttpResponseException(response()->json([
                'message'            => "Çok fazla başarısız giriş denemesi. {$minutes} dakika sonra tekrar deneyin.",
                'remaining_attempts' => 0,
                'locked'             => true,
                'retry_after'        => $retryAfter,
                'errors'             => ['phone' => ["Çok fazla başarısız giriş denemesi. {$minutes} dakika sonra tekrar deneyin."]],
            ], 429));
        }

        $user = User::query()->where('phone', $phone)->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            $remaining  = max(0, self::MAX_ATTEMPTS - RateLimiter::attempts($throttleKey));
            $isLocked   = $remaining === 0;
            $retryAfter = $isLocked ? RateLimiter::availableIn($throttleKey) : null;
            $message    = $isLocked
                ? 'Çok fazla başarısız giriş denemesi. Lütfen bekleyin.'
                : "Telefon numarası veya şifre hatalı. {$remaining} deneme hakkınız kaldı.";

            throw new HttpResponseException(response()->json([
                'message'            => $message,
                'remaining_attempts' => $remaining,
                'locked'             => $isLocked,
                'retry_after'        => $retryAfter,
                'errors'             => ['phone' => [$message]],
            ], $isLocked ? 429 : 422));
        }

        RateLimiter::clear($throttleKey);

        $user->update([
            'last_ip'       => $request->ip(),
            'last_location' => $credentials['location'] ?? $user->last_location,
            'last_login_at' => now(),
        ]);

        $this->claimGuestCart($request, $user);

        return response()->json($this->buildAuthPayload(
            $user,
            $tokenIssuer->issue($user, (string) $request->input('device_name', 'default')),
        ));
    }

    public function providers(OAuthProviderManager $providers): JsonResponse
    {
        return response()->json([
            'data' => $providers->statuses(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var ApiToken|null $token */
        $token = $request->attributes->get('api_token');

        if ($token) {
            $token->delete();
        } elseif ($request->bearerToken()) {
            ApiToken::query()
                ->where('token_hash', hash('sha256', $request->bearerToken()))
                ->delete();
        }

        return response()->json(['data' => ['status' => 'ok']]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Türkiye formatı: başındaki +90 / 0 / boşluk / - temizle → 10 hane */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        // +905xx → 5xx (13 hane → son 10)
        if (strlen($digits) === 12 && str_starts_with($digits, '90')) {
            $digits = substr($digits, 2);
        }

        // 05xx → 5xx
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    private function throttleKey(string $phone, string $ip): string
    {
        return 'login|' . $phone . '|' . $ip;
    }

    private function serializeUser(User $user): array
    {
        return [
            'id'                => $user->id,
            'public_uid'        => $user->public_uid,
            'customer_uid'      => $user->customer_uid,
            'sync_version'      => $user->sync_version,
            'name'              => $user->name,
            'phone'             => $user->phone,
            'email'             => $user->email,
            'avatar_url'        => $user->avatar_url ?? null,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'loyalty_points'    => (int) $user->loyalty_points,
            'loyalty_points_lifetime' => (int) $user->loyalty_points_lifetime,
            'is_vip'            => $user->isVipActive(),
            'vip_started_at'    => $user->vip_started_at?->toIso8601String(),
            'vip_expires_at'    => $user->vip_expires_at?->toIso8601String(),
            'ad_free'           => $user->isAdFree(),
        ];
    }

    /**
     * @param array{token: string, expires_at: \Carbon\CarbonImmutable} $tokenData
     */
    private function buildAuthPayload(User $user, array $tokenData): array
    {
        return [
            'user'       => $this->serializeUser($user),
            'token'      => $tokenData['token'],
            'token_type' => 'Bearer',
            'expires_at' => $tokenData['expires_at']->toIso8601String(),
        ];
    }

    private function claimGuestCart(Request $request, User $user): void
    {
        $cartToken = $request->header('X-Cart-Token') ?: $request->input('cart_token');
        $cartToken = $cartToken ? Str::limit((string) $cartToken, 64, '') : null;

        if (! $cartToken) {
            return;
        }

        $guestItems = CartItem::query()
            ->where('cart_token', $cartToken)
            ->get()
            ->groupBy(fn (CartItem $item): string => $item->tenant_id . '-' . $item->product_id);

        foreach ($guestItems as $items) {
            /** @var CartItem $firstItem */
            $firstItem = $items->first();

            $existing = CartItem::query()
                ->where('tenant_id', $firstItem->tenant_id)
                ->where('user_id', $user->id)
                ->where('product_id', $firstItem->product_id)
                ->first();

            $quantity = min(99, $items->sum('quantity') + ($existing?->quantity ?? 0));

            if ($existing) {
                $existing->update(['quantity' => $quantity]);
                $items->each->delete();
                continue;
            }

            $firstItem->update([
                'user_id'    => $user->id,
                'cart_token' => null,
                'quantity'   => $quantity,
            ]);

            $items->slice(1)->each->delete();
        }

        $this->claimGuestCartCoupon($cartToken, $user);
    }

    private function claimGuestCartCoupon(string $cartToken, User $user): void
    {
        $guestCoupons = CartCoupon::query()
            ->where('cart_token', $cartToken)
            ->get();

        foreach ($guestCoupons as $guestCoupon) {
            $existing = CartCoupon::query()
                ->where('tenant_id', $guestCoupon->tenant_id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing) {
                $guestCoupon->delete();

                continue;
            }

            $guestCoupon->update([
                'user_id' => $user->id,
                'cart_token' => null,
            ]);
        }
    }
}
