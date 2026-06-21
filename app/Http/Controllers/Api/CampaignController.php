<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $campaigns = Campaign::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $campaigns]);
    }

    public function show(Campaign $campaign): JsonResponse
    {
        return response()->json(['data' => $campaign]);
    }

    public function exportJson(): JsonResponse
    {
        $campaigns = Campaign::query()
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'exported_at' => now(),
            'total' => $campaigns->count(),
            'data' => $campaigns,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:campaigns',
            'description' => 'nullable|string',
            'body' => 'nullable|string',
            'banner_image_url' => 'nullable|url',
            'badge_label' => 'nullable|string|max:50',
            'color_hex' => 'nullable|string|regex:/#[a-fA-F0-9]{6}/',
            'discount_type' => 'in:percent,fixed',
            'discount_value' => 'numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $campaign = Campaign::create($validated);

        return response()->json(['data' => $campaign], 201);
    }

    public function update(Campaign $campaign, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'slug' => 'string|max:255|unique:campaigns,slug,' . $campaign->id,
            'description' => 'nullable|string',
            'body' => 'nullable|string',
            'banner_image_url' => 'nullable|url',
            'badge_label' => 'nullable|string|max:50',
            'color_hex' => 'nullable|string|regex:/#[a-fA-F0-9]{6}/',
            'discount_type' => 'in:percent,fixed',
            'discount_value' => 'numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $campaign->update($validated);

        return response()->json(['data' => $campaign]);
    }

    public function destroy(Campaign $campaign): JsonResponse
    {
        $campaign->delete();

        return response()->json(['message' => 'Campaign deleted']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaigns' => 'required|array',
            'campaigns.*.id' => 'required|exists:campaigns,id',
            'campaigns.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['campaigns'] as $item) {
            Campaign::find($item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['message' => 'Order updated']);
    }
}
