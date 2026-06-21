<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageBlock;
use App\Support\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HomepageBlockController extends Controller
{
    private const TYPES = [
        'carousel_slide',
        'hero',
        'campaign',
        'content',
        'product_slider',
    ];

    public function index(): View
    {
        return view('admin.homepage-blocks.index', [
            'blocks' => HomepageBlock::query()->orderBy('sort_order')->paginate(30),
            'types' => self::TYPES,
        ]);
    }

    public function store(Request $request, TenantResolver $tenants): RedirectResponse
    {
        $tenant = $tenants->resolve($request);
        $validated = $this->validated($request);

        HomepageBlock::query()->create($validated + [
            'tenant_id' => $tenant->id,
        ]);

        $this->clearHomepageCache($tenant->id);

        return back()->with('status', 'Ana sayfa bloku eklendi.');
    }

    public function update(Request $request, HomepageBlock $homepageBlock): RedirectResponse
    {
        $homepageBlock->update($this->validated($request));
        $this->clearHomepageCache((int) $homepageBlock->tenant_id);

        return back()->with('status', 'Ana sayfa bloku güncellendi.');
    }

    public function destroy(HomepageBlock $homepageBlock): RedirectResponse
    {
        $tenantId = (int) $homepageBlock->tenant_id;
        $homepageBlock->delete();
        $this->clearHomepageCache($tenantId);

        return back()->with('status', 'Ana sayfa bloku silindi.');
    }

    private function validated(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', Rule::in(self::TYPES)],
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:51200'],
            'link_url' => ['nullable', 'string', 'max:500'],
            'link_label' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
            'show_on_mobile' => ['sometimes', 'boolean'],
            'show_on_web' => ['sometimes', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            $url = (string) $request->input('link_url');

            if ($url !== '' && ! $this->isSafeUrl($url)) {
                $validator->errors()->add('link_url', 'Yalnizca site ici / ile baslayan veya http(s) URL girilebilir.');
            }

            $imageUrl = (string) $request->input('image_url');

            if ($imageUrl !== '' && ! $this->isSafeUrl($imageUrl)) {
                $validator->errors()->add('image_url', 'Yalnizca site ici / ile baslayan veya http(s) gorsel URL girilebilir.');
            }
        });

        $validated = $validator->validate();

        $imageUrl = $validated['image_url'] ?? null;
        if ($request->hasFile('image_file')) {
            $imageUrl = Storage::url($request->file('image_file')->store('homepage/slider', 'public'));
        }

        return [
            'type' => $validated['type'],
            'title' => isset($validated['title']) && trim($validated['title']) !== '' ? trim($validated['title']) : null,
            'subtitle' => $validated['subtitle'] ?? null,
            'image_url' => $imageUrl,
            'link_url' => $validated['link_url'] ?? null,
            'link_label' => $validated['link_label'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
            'show_on_mobile' => $request->boolean('show_on_mobile'),
            'show_on_web' => $request->boolean('show_on_web'),
        ];
    }

    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return false;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    private function clearHomepageCache(int $tenantId): void
    {
        foreach (['web', 'mobile'] as $channel) {
            Cache::forget("tenant:{$tenantId}:content:homepage:v3:{$channel}");
        }
    }
}
