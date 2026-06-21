<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'string', 'in:unread,read,all'],
        ]);

        $user = $request->user();
        $limit = (int) ($validated['limit'] ?? 25);
        $status = $validated['status'] ?? 'unread';

        $query = Notification::query()
            ->where('user_id', $user->id);

        if ($status === 'unread') {
            $query->whereNull('read_at');
        } elseif ($status === 'read') {
            $query->whereNotNull('read_at');
        }

        $notifications = $query->latest()->limit($limit)->get();

        return response()->json([
            'data' => $notifications->map(fn (Notification $notification): array => $this->serialize($notification))->values(),
            'meta' => [
                'unread_count' => Notification::query()
                    ->where('user_id', $user->id)
                    ->whereNull('read_at')
                    ->count(),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $notificationId): JsonResponse
    {
        $notification = Notification::query()
            ->where('id', $notificationId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'data' => $this->serialize($notification->fresh()),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'data' => ['status' => 'ok'],
        ]);
    }

    public function destroy(Request $request, string $notificationId): JsonResponse
    {
        Notification::query()
            ->where('id', $notificationId)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json([
            'data' => ['status' => 'deleted'],
        ]);
    }

    public function storeDeviceToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'device_type' => ['nullable', 'string', 'in:ios,android'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $deviceToken = DeviceToken::query()->updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'device_type' => $validated['device_type'] ?? 'ios',
                'device_name' => $validated['device_name'] ?? null,
                'is_active' => true,
            ]
        );

        return response()->json([
            'data' => [
                'id' => $deviceToken->id,
                'status' => 'registered',
            ],
        ], 201);
    }

    private function serialize(Notification $notification): array
    {
        $payload = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => (string) $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'action_url' => $payload['action_url'] ?? null,
            'image_url' => $payload['image_url'] ?? null,
            'payload' => isset($payload['payload']) && is_array($payload['payload']) ? $payload['payload'] : $payload,
            'broadcast_id' => isset($payload['broadcast_id']) ? (int) $payload['broadcast_id'] : null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'sent_at' => $notification->sent_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
