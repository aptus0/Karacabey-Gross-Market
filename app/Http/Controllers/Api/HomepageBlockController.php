<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomepageBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomepageBlockController extends Controller
{
    public function index(): JsonResponse
    {
        $blocks = HomepageBlock::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $blocks]);
    }

    public function show(HomepageBlock $block): JsonResponse
    {
        return response()->json(['data' => $block]);
    }

    public function exportJson(): JsonResponse
    {
        $blocks = HomepageBlock::query()
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'exported_at' => now(),
            'total' => $blocks->count(),
            'data' => $blocks,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:hero,featured,category,promo',
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image_url' => 'nullable|url',
            'link_url' => 'nullable|string',
            'link_label' => 'nullable|string|max:100',
            'payload' => 'nullable|array',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $block = HomepageBlock::create($validated);

        return response()->json(['data' => $block], 201);
    }

    public function update(HomepageBlock $block, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'in:hero,featured,category,promo',
            'title' => 'string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image_url' => 'nullable|url',
            'link_url' => 'nullable|string',
            'link_label' => 'nullable|string|max:100',
            'payload' => 'nullable|array',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $block->update($validated);

        return response()->json(['data' => $block]);
    }

    public function destroy(HomepageBlock $block): JsonResponse
    {
        $block->delete();

        return response()->json(['message' => 'Block deleted']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'blocks' => 'required|array',
            'blocks.*.id' => 'required|exists:homepage_blocks,id',
            'blocks.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['blocks'] as $item) {
            HomepageBlock::find($item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['message' => 'Order updated']);
    }
}
