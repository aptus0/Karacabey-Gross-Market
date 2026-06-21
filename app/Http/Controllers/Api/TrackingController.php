<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TrackingController extends Controller
{
    public function consent(Request $request, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $validated = $request->validate([
            'source' => ['nullable', 'string', 'max:40'],
            'consent' => ['required', 'array'],
            'consent.version' => ['nullable', 'string', 'max:40'],
            'consent.necessary' => ['nullable', 'boolean'],
            'consent.analytics' => ['nullable', 'boolean'],
            'consent.marketing' => ['nullable', 'boolean'],
            'consent.personalization' => ['nullable', 'boolean'],
            'consent.performance' => ['nullable', 'boolean'],
        ]);

        $consent = $validated['consent'];

        DB::table('cookie_consents')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()?->id,
            'anonymous_id' => $this->headerValue($request, 'X-Customer-UID'),
            'session_id' => $this->headerValue($request, 'X-Session-UID'),
            'cart_token' => $this->headerValue($request, 'X-Cart-Token'),
            'source' => $validated['source'] ?? null,
            'necessary' => true,
            'analytics' => (bool) ($consent['analytics'] ?? false),
            'marketing' => (bool) ($consent['marketing'] ?? false),
            'personalization' => (bool) ($consent['personalization'] ?? false),
            'performance' => (bool) ($consent['performance'] ?? false),
            'consent_version' => $consent['version'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'request_id' => $this->headerValue($request, 'X-Request-ID'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => ['accepted' => true]]);
    }

    public function events(Request $request, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $validated = $request->validate([
            'event_id' => ['nullable', 'string', 'max:100'],
            'event_name' => ['required', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'in:necessary,analytics,marketing,personalization,performance'],
            'anonymous_id' => ['nullable', 'string', 'max:100'],
            'session_id' => ['nullable', 'string', 'max:100'],
            'cart_token' => ['nullable', 'string', 'max:100'],
            'page_url' => ['nullable', 'string', 'max:4000'],
            'referrer' => ['nullable', 'string', 'max:4000'],
            'source' => ['nullable', 'string', 'max:120'],
            'medium' => ['nullable', 'string', 'max:120'],
            'campaign' => ['nullable', 'string', 'max:160'],
            'product_id' => ['nullable'],
            'order_id' => ['nullable'],
            'value_cents' => ['nullable', 'integer'],
            'currency' => ['nullable', 'string', 'max:8'],
            'event_data' => ['nullable', 'array'],
            'consent' => ['nullable', 'array'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        DB::table('tracking_events')->insertOrIgnore([
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()?->id,
            'event_id' => $validated['event_id'] ?? null,
            'event_name' => $validated['event_name'],
            'category' => $validated['category'] ?? 'analytics',
            'anonymous_id' => $validated['anonymous_id'] ?? $this->headerValue($request, 'X-Customer-UID'),
            'session_id' => $validated['session_id'] ?? $this->headerValue($request, 'X-Session-UID'),
            'cart_token' => $validated['cart_token'] ?? $this->headerValue($request, 'X-Cart-Token'),
            'page_url' => $validated['page_url'] ?? null,
            'referrer' => $validated['referrer'] ?? null,
            'source' => $validated['source'] ?? null,
            'medium' => $validated['medium'] ?? null,
            'campaign' => $validated['campaign'] ?? null,
            'product_id' => $this->numericId($validated['product_id'] ?? null),
            'order_id' => $this->numericId($validated['order_id'] ?? null),
            'value_cents' => $validated['value_cents'] ?? null,
            'currency' => $validated['currency'] ?? 'TRY',
            'event_data' => isset($validated['event_data']) ? json_encode($validated['event_data'], JSON_UNESCAPED_UNICODE) : null,
            'consent_snapshot' => isset($validated['consent']) ? json_encode($validated['consent'], JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'occurred_at' => isset($validated['occurred_at']) ? Carbon::parse($validated['occurred_at']) : now(),
            'created_at' => now(),
        ]);

        return response()->json(['data' => ['accepted' => true]]);
    }

    private function headerValue(Request $request, string $name): ?string
    {
        $value = trim((string) $request->header($name));

        return $value === '' ? null : Str::limit($value, 100, '');
    }

    private function numericId(mixed $value): ?int
    {
        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value;
        }

        return null;
    }
}
