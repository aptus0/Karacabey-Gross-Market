<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Coupon;
use App\Services\ImageUploadService;
use App\Support\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function __construct(private readonly ImageUploadService $images) {}

    public function index(): View
    {
        return view('admin.campaigns.index', [
            'campaigns' => Campaign::query()->with('coupons')->withCount('coupons')->orderBy('sort_order')->latest()->paginate(20),
            'coupons'   => Coupon::query()->with('campaign')->latest()->paginate(20),
        ]);
    }

    public function store(Request $request, TenantResolver $tenants): RedirectResponse
    {
        $tenant    = $tenants->resolve($request);
        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'slug'             => ['nullable', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'body'             => ['nullable', 'string'],
            'banner_image'     => ['nullable', 'file', 'mimes:' . ImageUploadService::MIMES, 'max:' . ImageUploadService::MAX_KB],
            'banner_image_url' => ['nullable', 'url', 'max:500'],
            'meta_image_url'   => ['nullable', 'url', 'max:500'],
            'badge_label'      => ['nullable', 'string', 'max:60'],
            'color_hex'        => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'discount_type'    => ['required', 'in:fixed,percent'],
            'discount_value'   => ['required', 'integer', 'min:0'],
            'starts_at'        => ['nullable', 'date'],
            'ends_at'          => ['nullable', 'date'],
            'sort_order'       => ['nullable', 'integer', 'min:0'],
            'is_active'        => ['sometimes', 'boolean'],
            'show_on_mobile'   => ['sometimes', 'boolean'],
            'show_on_web'      => ['sometimes', 'boolean'],
            'seo_title'        => ['nullable', 'string', 'max:255'],
            'seo_description'  => ['nullable', 'string', 'max:500'],
        ]);

        $imagePath = null;
        if ($request->hasFile('banner_image')) {
            $imagePath = $this->images->store(
                file: $request->file('banner_image'),
                folder: 'campaigns',
                maxWidth: 1200,
                maxHeight: 630,
            );
        }

        $validated['slug']       = $validated['slug'] ?: Str::slug($validated['name']);
        $validated['image_path'] = $imagePath;
        $validated['seo']        = [
            'title'       => $validated['seo_title'] ?? $validated['name'],
            'description' => $validated['seo_description'] ?? $validated['description'] ?? null,
        ];
        $validated['is_active']  = $request->boolean('is_active');
        $validated['show_on_mobile'] = $request->boolean('show_on_mobile');
        $validated['show_on_web'] = $request->boolean('show_on_web');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['color_hex']  = $validated['color_hex'] ?? '#FF7A00';

        unset($validated['seo_title'], $validated['seo_description'], $validated['banner_image']);

        Campaign::query()->create($validated + ['tenant_id' => $tenant->id]);
        $this->bustContentCache($tenant->id);

        return back()->with('status', 'Kampanya oluşturuldu.');
    }

    public function edit(Campaign $campaign): View
    {
        return view('admin.campaigns.edit', [
            'campaign' => $campaign,
        ]);
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'slug'             => ['nullable', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'body'             => ['nullable', 'string'],
            'banner_image'     => ['nullable', 'file', 'mimes:' . ImageUploadService::MIMES, 'max:' . ImageUploadService::MAX_KB],
            'banner_image_url' => ['nullable', 'url', 'max:500'],
            'meta_image_url'   => ['nullable', 'url', 'max:500'],
            'badge_label'      => ['nullable', 'string', 'max:60'],
            'color_hex'        => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'discount_type'    => ['required', 'in:fixed,percent'],
            'discount_value'   => ['required', 'integer', 'min:0'],
            'starts_at'        => ['nullable', 'date'],
            'ends_at'          => ['nullable', 'date'],
            'sort_order'       => ['nullable', 'integer', 'min:0'],
            'is_active'        => ['sometimes', 'boolean'],
            'show_on_mobile'   => ['sometimes', 'boolean'],
            'show_on_web'      => ['sometimes', 'boolean'],
            'seo_title'        => ['nullable', 'string', 'max:255'],
            'seo_description'  => ['nullable', 'string', 'max:500'],
        ]);

        $imagePath = $campaign->image_path;
        if ($request->hasFile('banner_image')) {
            $this->images->delete($campaign->image_path);
            $imagePath = $this->images->store(
                file: $request->file('banner_image'),
                folder: 'campaigns',
                maxWidth: 1200,
                maxHeight: 630,
            );
        }

        $validated['slug']       = $validated['slug'] ?: Str::slug($validated['name']);
        $validated['image_path'] = $imagePath;
        $validated['is_active']  = $request->boolean('is_active');
        $validated['show_on_mobile'] = $request->boolean('show_on_mobile');
        $validated['show_on_web'] = $request->boolean('show_on_web');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['color_hex']  = $validated['color_hex'] ?? '#FF7A00';
        $validated['seo']        = [
            'title'       => $validated['seo_title'] ?? $validated['name'],
            'description' => $validated['seo_description'] ?? $validated['description'] ?? null,
        ];

        unset($validated['seo_title'], $validated['seo_description'], $validated['banner_image']);

        $campaign->update($validated);
        $this->bustContentCache($campaign->tenant_id);

        return back()->with('status', 'Kampanya güncellendi.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $tenantId = $campaign->tenant_id;
        $campaign->delete();
        $this->bustContentCache($tenantId);

        return redirect()->route('admin.campaigns.index')->with('status', 'Kampanya silindi.');
    }

    public function storeCoupon(Request $request, TenantResolver $tenants): RedirectResponse
    {
        $tenant   = $tenants->resolve($request);
        $validated = $request->validate([
            'campaign_id'          => ['nullable', 'exists:campaigns,id'],
            'code'                 => ['required', 'alpha_dash', 'max:64', Rule::unique('coupons', 'code')->where('tenant_id', $tenant->id)],
            'discount_type'        => ['required', 'in:fixed,percent'],
            'discount_value'       => ['required', 'integer', 'min:0'],
            'minimum_order_cents'  => ['nullable', 'integer', 'min:0'],
            'usage_limit'          => ['nullable', 'integer', 'min:1'],
            'starts_at'            => ['nullable', 'date'],
            'ends_at'              => ['nullable', 'date'],
            'is_active'            => ['sometimes', 'boolean'],
        ]);

        $validated['code']                = Str::upper($validated['code']);
        $validated['minimum_order_cents'] = $validated['minimum_order_cents'] ?? 0;
        $validated['is_active']           = $request->boolean('is_active');

        Coupon::query()->create($validated + ['tenant_id' => $tenant->id]);

        return back()->with('status', 'Kupon oluşturuldu.');
    }

    private function bustContentCache(int $tenantId): void
    {
        Cache::forget("tenant:{$tenantId}:content:campaigns:v2");
        Cache::forget("tenant:{$tenantId}:content:campaigns:v2:web");
        Cache::forget("tenant:{$tenantId}:content:campaigns:v2:mobile");
        Cache::forget("tenant:{$tenantId}:content:homepage:v3:web");
        Cache::forget("tenant:{$tenantId}:content:homepage:v3:mobile");
    }
}
