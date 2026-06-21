<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoryController extends Controller
{
    public function index(): JsonResponse
    {
        $stories = Story::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $stories]);
    }

    public function show(Story $story): JsonResponse
    {
        return response()->json(['data' => $story]);
    }

    public function exportJson(): JsonResponse
    {
        $stories = Story::query()
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'exported_at' => now(),
            'total' => $stories->count(),
            'data' => $stories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'category_slug' => 'nullable|string|max:255',
            'custom_url' => 'nullable|url',
            'gradient_start' => 'nullable|regex:/#[a-fA-F0-9]{6}/',
            'gradient_end' => 'nullable|regex:/#[a-fA-F0-9]{6}/',
            'icon' => 'nullable|string|max:100',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $story = Story::create($validated);

        return response()->json(['data' => $story], 201);
    }

    public function update(Story $story, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'category_slug' => 'nullable|string|max:255',
            'custom_url' => 'nullable|url',
            'gradient_start' => 'nullable|regex:/#[a-fA-F0-9]{6}/',
            'gradient_end' => 'nullable|regex:/#[a-fA-F0-9]{6}/',
            'icon' => 'nullable|string|max:100',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $story->update($validated);

        return response()->json(['data' => $story]);
    }

    public function destroy(Story $story): JsonResponse
    {
        $story->delete();

        return response()->json(['message' => 'Story deleted']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'stories' => 'required|array',
            'stories.*.id' => 'required|exists:stories,id',
            'stories.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['stories'] as $item) {
            Story::find($item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['message' => 'Order updated']);
    }
}
