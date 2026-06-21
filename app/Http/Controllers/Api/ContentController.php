<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\HomepageBlock;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\Story;
use App\Support\CdnUrl;
use App\Support\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ContentController extends Controller
{
    public function homepage(Request $request, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $channel = $this->normalizeChannel($request->query('channel'));
        $payload = Cache::remember("tenant:{$tenant->id}:content:homepage:v3:{$channel}", now()->addSeconds((int) config('web_performance.cache.content_ttl_seconds', 300)), function () use ($tenant, $channel): array {
            $channelColumn = $channel === 'mobile' ? 'show_on_mobile' : 'show_on_web';

            return [
                'stories' => Story::query()
                    ->whereBelongsTo($tenant)
                    ->where('is_active', true)
                    ->where($channelColumn, true)
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn (Story $story): array => $this->serializeStory($story))
                    ->values()
                    ->all(),
                'blocks' => HomepageBlock::query()
                    ->whereBelongsTo($tenant)
                    ->where('is_active', true)
                    ->where($channelColumn, true)
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn (HomepageBlock $block): array => $this->serializeBlock($block))
                    ->values()
                    ->all(),
                'campaigns' => $this->activeCampaigns($tenant->id, $channelColumn)
                    ->map(fn (Campaign $campaign): array => $this->serializeCampaign($campaign))
                    ->values()
                    ->all(),
            ];
        });

        return response()->json([
            'data' => $payload,
        ]);
    }

    public function stories(Request $request, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $channel = $this->normalizeChannel($request->query('channel', 'mobile'));
        $channelColumn = $channel === 'mobile' ? 'show_on_mobile' : 'show_on_web';

        $stories = Story::query()
            ->whereBelongsTo($tenant)
            ->where('is_active', true)
            ->where($channelColumn, true)
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Story $story): array => $this->serializeStory($story))
            ->values()
            ->all();

        return response()->json(['data' => $stories]);
    }

    public function pages(Request $request, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $pages = Cache::remember("tenant:{$tenant->id}:content:pages:v1", now()->addSeconds((int) config('web_performance.cache.content_ttl_seconds', 300)), function () use ($tenant): array {
            return Page::query()
                ->whereBelongsTo($tenant)
                ->where('is_published', true)
                ->orderBy('title')
                ->get()
                ->map(fn (Page $page): array => $this->serializePage($page, includeBody: false))
                ->values()
                ->all();
        });

        return response()->json(['data' => $pages]);
    }

    public function page(Request $request, TenantResolver $tenants, string $slug): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $page = Cache::remember("tenant:{$tenant->id}:content:page:{$slug}:v1", now()->addSeconds((int) config('web_performance.cache.content_ttl_seconds', 300)), function () use ($tenant, $slug): array {
            $page = Page::query()
                ->whereBelongsTo($tenant)
                ->where('is_published', true)
                ->where('slug', $slug)
                ->firstOrFail();

            return $this->serializePage($page);
        });

        return response()->json(['data' => $page]);
    }

    public function campaigns(Request $request, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $channel = $this->normalizeChannel($request->query('channel'));
        $channelColumn = $channel === 'mobile' ? 'show_on_mobile' : 'show_on_web';
        $campaigns = Cache::remember("tenant:{$tenant->id}:content:campaigns:v2:{$channel}", now()->addSeconds((int) config('web_performance.cache.content_ttl_seconds', 300)), function () use ($tenant, $channelColumn): array {
            return $this->activeCampaigns($tenant->id, $channelColumn)
                ->map(fn (Campaign $campaign): array => $this->serializeCampaign($campaign))
                ->values()
                ->all();
        });

        return response()->json([
            'data' => $campaigns,
        ]);
    }

    public function campaign(Request $request, TenantResolver $tenants, string $slug): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $campaign = Cache::remember("tenant:{$tenant->id}:content:campaign:{$slug}:v2", now()->addSeconds((int) config('web_performance.cache.content_ttl_seconds', 300)), function () use ($tenant, $slug): array {
            $campaign = Campaign::query()
                ->where('tenant_id', $tenant->id)
                ->where('slug', $slug)
                ->where('is_active', true)
                ->withCount([
                    'coupons as active_coupons_count' => fn ($query) => $query->where('is_active', true),
                ])
                ->with([
                    'coupons' => fn ($query) => $query
                        ->where('is_active', true)
                        ->orderBy('id'),
                ])
                ->firstOrFail();

            return $this->serializeCampaign($campaign, full: true);
        });

        return response()->json(['data' => $campaign]);
    }

    public function marketing(Request $request, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $payload = Cache::remember("tenant:{$tenant->id}:content:marketing:v2", now()->addSeconds((int) config('web_performance.cache.content_ttl_seconds', 300)), function () use ($tenant): array {
            $setting = $tenant->marketingSetting()->first();

            if (! $setting) {
                return [
                    'tracking_enabled' => false,
                    'announcement_text' => null,
                ];
            }

            return $setting->publicConfig();
        });

        return response()->json([
            'data' => $payload,
        ]);
    }

    public function navigation(Request $request, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $payload = Cache::remember("tenant:{$tenant->id}:content:navigation:v1", now()->addSeconds((int) config('web_performance.cache.content_ttl_seconds', 300)), function () use ($tenant): array {
            $items = NavigationItem::query()
                ->whereBelongsTo($tenant)
                ->where('is_active', true)
                ->select(['id', 'tenant_id', 'placement', 'label', 'url', 'icon', 'sort_order'])
                ->orderBy('placement')
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get()
                ->groupBy('placement')
                ->map(fn ($items) => $items->map(fn (NavigationItem $item): array => $this->serializeNavigationItem($item))->values());

            return collect(array_keys(NavigationItem::PLACEMENTS))
                ->mapWithKeys(fn (string $placement): array => [$placement => $items->get($placement, collect())->values()->all()])
                ->all();
        });

        return response()->json([
            'data' => $payload,
        ]);
    }

    private function activeCampaigns(int $tenantId, ?string $channelColumn = null)
    {
        return Campaign::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when($channelColumn, fn ($query) => $query->where($channelColumn, true))
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->withCount([
                'coupons as active_coupons_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->latest()
            ->get();
    }

    private function serializePage(Page $page, bool $includeBody = true): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'group' => $page->group,
            'body' => $includeBody ? $page->body : null,
            'seo' => [
                'title' => $page->seo_title ?: $page->title,
                'description' => $page->seo_description,
                'image_url' => $page->meta_image_url,
            ],
        ];
    }

    private function serializeBlock(HomepageBlock $block): array
    {
        return [
            'id' => $block->id,
            'type' => $block->type,
            'title' => $block->title,
            'subtitle' => $block->subtitle,
            'image_url' => CdnUrl::for($block->image_url),
            'link_url' => $block->link_url,
            'link_label' => $block->link_label,
            'payload' => $block->payload,
        ];
    }

    private function serializeCampaign(Campaign $campaign, bool $full = false): array
    {
        $data = [
            'id'               => $campaign->id,
            'name'             => $campaign->name,
            'slug'             => $campaign->slug,
            'description'      => $campaign->description,
            'banner_image_url' => CdnUrl::for($campaign->banner_url ?? $campaign->banner_image_url),
            'meta_image_url'   => CdnUrl::for($campaign->meta_image_url),
            'badge_label'      => $campaign->badge_label,
            'color_hex'        => $campaign->color_hex ?? '#FF7A00',
            'discount_type'    => $campaign->discount_type,
            'discount_value'   => $campaign->discount_value,
            'discount_label'   => $campaign->discount_label,
            'starts_at'        => $campaign->starts_at?->toIso8601String(),
            'ends_at'          => $campaign->ends_at?->toIso8601String(),
            'coupons_count'    => (int) ($campaign->active_coupons_count ?? 0),
            'seo'              => $campaign->seo,
            'influencer_name'  => $campaign->influencer_name,
            'influencer_handle'=> $campaign->influencer_handle,
            'promo_code'       => $campaign->promo_code,
            'cta_url'          => $campaign->cta_url,
            'content_type'     => $campaign->content_type,
        ];

        if ($full) {
            $data['body'] = $campaign->body;
            $coupons = $campaign->relationLoaded('coupons')
                ? $campaign->coupons
                : $campaign->coupons()->where('is_active', true)->get();

            $data['coupons'] = $coupons
                ->map(fn ($c) => [
                    'code'           => $c->code,
                    'discount_type'  => $c->discount_type,
                    'discount_value' => $c->discount_value,
                    'ends_at'        => $c->ends_at?->toIso8601String(),
                ])
                ->values()
                ->all();
        }

        return $data;
    }

    private function serializeStory(Story $story): array
    {
        $imageUrl = CdnUrl::for($story->image_url);

        return [
            'id' => $story->id,
            'title' => $story->title,
            'subtitle' => $story->subtitle,
            'image_url' => $imageUrl,
            'cover_image_url' => $imageUrl,
            'category_slug' => $story->category_slug,
            'custom_url' => $story->custom_url,
            'deep_link' => $story->custom_url ?: ($story->category_slug ? '/categories/'.$story->category_slug : null),
            'gradient_start' => $story->gradient_start,
            'gradient_end' => $story->gradient_end,
            'icon' => $story->icon,
            'influencer_name' => $story->influencer_name,
            'influencer_handle' => $story->influencer_handle,
            'promo_code' => $story->promo_code,
            'cta_url' => $story->cta_url,
            'content_type' => $story->content_type,
        ];
    }

    private function serializeNavigationItem(NavigationItem $item): array
    {
        return [
            'id' => $item->id,
            'label' => $item->label,
            'url' => $item->url,
            'icon' => $item->icon,
            'external' => str_starts_with($item->url, 'http://') || str_starts_with($item->url, 'https://'),
        ];
    }

    private function normalizeChannel(mixed $channel): string
    {
        $value = is_string($channel) ? strtolower(trim($channel)) : '';

        return $value === 'mobile' ? 'mobile' : 'web';
    }
}
