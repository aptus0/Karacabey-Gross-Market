<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Services\ImageUploadService;
use App\Support\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class StoryController extends Controller
{
    public function __construct(private readonly ImageUploadService $images) {}

    public function index(Request $request, TenantResolver $tenants): View
    {
        $tenant  = $tenants->resolve($request);
        $stories = Story::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('sort_order')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.stories.index', compact('stories'));
    }

    public function store(Request $request, TenantResolver $tenants): RedirectResponse
    {
        $tenant    = $tenants->resolve($request);
        $validated = $request->validate([
            'title'          => ['nullable', 'string', 'max:120'],
            'subtitle'       => ['nullable', 'string', 'max:240'],
            'image'          => ['nullable', 'file', 'mimes:' . ImageUploadService::MIMES, 'max:' . ImageUploadService::MAX_KB],
            'category_slug'  => ['nullable', 'string', 'max:120'],
            'custom_url'     => ['nullable', 'string', 'max:500'],
            'gradient_start' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'gradient_end'   => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon'           => ['nullable', 'string', 'max:80'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
            'is_active'      => ['sometimes', 'boolean'],
            'show_on_mobile' => ['sometimes', 'boolean'],
            'show_on_web'    => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['custom_url']) && ! $this->isSafeUrl($validated['custom_url'])) {
            return back()->withErrors(['custom_url' => 'Site içi / ile başlayan veya geçerli http(s) URL girin.'])->withInput();
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $this->images->store(
                file: $request->file('image'),
                folder: 'stories',
                maxWidth: 600,
                maxHeight: 600,
            );
        }

        Story::query()->create([
            'tenant_id'      => $tenant->id,
            'title'          => $this->nullableText($validated['title'] ?? null),
            'subtitle'       => $validated['subtitle'] ?? null,
            'image_path'     => $imagePath,
            'category_slug'  => $validated['category_slug'] ?? null,
            'custom_url'     => $validated['custom_url'] ?? null,
            'gradient_start' => $validated['gradient_start'] ?? '#FF7A00',
            'gradient_end'   => $validated['gradient_end'] ?? '#FF3300',
            'icon'           => $validated['icon'] ?? 'tag.fill',
            'sort_order'     => $validated['sort_order'] ?? 0,
            'is_active'      => $request->boolean('is_active', true),
            'show_on_mobile' => $request->boolean('show_on_mobile'),
            'show_on_web'    => $request->boolean('show_on_web'),
        ]);

        $this->bustCache($tenant->id);

        return back()->with('status', 'Story oluşturuldu.');
    }

    public function update(Request $request, Story $story): RedirectResponse
    {
        $validated = $request->validate([
            'title'          => ['nullable', 'string', 'max:120'],
            'subtitle'       => ['nullable', 'string', 'max:240'],
            'image'          => ['nullable', 'file', 'mimes:' . ImageUploadService::MIMES, 'max:' . ImageUploadService::MAX_KB],
            'category_slug'  => ['nullable', 'string', 'max:120'],
            'custom_url'     => ['nullable', 'string', 'max:500'],
            'gradient_start' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'gradient_end'   => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon'           => ['nullable', 'string', 'max:80'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
            'is_active'      => ['sometimes', 'boolean'],
            'show_on_mobile' => ['sometimes', 'boolean'],
            'show_on_web'    => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['custom_url']) && ! $this->isSafeUrl($validated['custom_url'])) {
            return back()->withErrors(['custom_url' => 'Site içi / ile başlayan veya geçerli http(s) URL girin.'])->withInput();
        }

        $imagePath = $story->image_path;

        if ($request->hasFile('image')) {
            $this->images->delete($story->image_path);
            $imagePath = $this->images->store(
                file: $request->file('image'),
                folder: 'stories',
                maxWidth: 600,
                maxHeight: 600,
            );
        }

        $story->update([
            'title'          => $this->nullableText($validated['title'] ?? null),
            'subtitle'       => $validated['subtitle'] ?? null,
            'image_path'     => $imagePath,
            'category_slug'  => $validated['category_slug'] ?? null,
            'custom_url'     => $validated['custom_url'] ?? null,
            'gradient_start' => $validated['gradient_start'] ?? $story->gradient_start,
            'gradient_end'   => $validated['gradient_end'] ?? $story->gradient_end,
            'icon'           => $validated['icon'] ?? $story->icon,
            'sort_order'     => $validated['sort_order'] ?? $story->sort_order,
            'is_active'      => $request->boolean('is_active'),
            'show_on_mobile' => $request->boolean('show_on_mobile'),
            'show_on_web'    => $request->boolean('show_on_web'),
        ]);

        $this->bustCache($story->tenant_id);

        return back()->with('status', 'Story güncellendi.');
    }

    public function destroy(Story $story): RedirectResponse
    {
        $tenantId = $story->tenant_id;
        $this->images->delete($story->image_path);
        $story->delete();
        $this->bustCache($tenantId);

        return back()->with('status', 'Story silindi.');
    }

    private function bustCache(int $tenantId): void
    {
        Cache::forget("tenant:{$tenantId}:content:stories:v1");
        Cache::forget("tenant:{$tenantId}:content:homepage:v3:web");
        Cache::forget("tenant:{$tenantId}:content:homepage:v3:mobile");
    }

    private function nullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        return (str_starts_with($url, '/') && ! str_starts_with($url, '//'))
            || (filter_var($url, FILTER_VALIDATE_URL) !== false
                && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true));
    }
}
