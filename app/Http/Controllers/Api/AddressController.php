<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()
            ->addresses()
            ->latest()
            ->get()
            ->map(fn (Address $address): array => $this->serialize($address));

        return response()->json(['data' => $addresses]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        if ($validated['is_default'] ?? false) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address = $request->user()->addresses()->create($validated);

        return response()->json(['data' => $this->serialize($address)], 201);
    }

    public function update(Request $request, Address $address): JsonResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $validated = $this->validated($request, partial: true);

        if ($validated['is_default'] ?? false) {
            $request->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
        }

        $address->update($validated);

        return response()->json(['data' => $this->serialize($address->fresh())]);
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        abort_unless($address->user_id === $request->user()->id, 403);

        $address->delete();

        return response()->json(['data' => ['status' => 'deleted']]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => ['sometimes', 'string', 'max:80'],
            'recipient_name' => [$required, 'string', 'max:120'],
            'phone' => [$required, 'string', 'max:20'],
            'city' => [$required, 'string', 'max:120'],
            'district' => [$required, 'string', 'max:120'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'address_line' => [$required, 'string', 'max:500'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }

    private function serialize(Address $address): array
    {
        return [
            'id' => $address->id,
            'title' => $address->title,
            'recipient_name' => $address->recipient_name,
            'phone' => $address->phone,
            'city' => $address->city,
            'district' => $address->district,
            'neighborhood' => $address->neighborhood,
            'address_line' => $address->address_line,
            'postal_code' => $address->postal_code,
            'is_default' => $address->is_default,
        ];
    }
}
