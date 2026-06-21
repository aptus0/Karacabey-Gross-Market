<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function sendTestNotification(Request $request): JsonResponse
    {
        $user = $request->user();
        $service = new PushNotificationService();

        $service->sendToUser($user, 'Test Title', 'This is a test notification', [
            'type' => 'test',
            'message' => 'Testing push notification system',
        ]);

        return response()->json([
            'data' => [
                'status' => 'notification sent',
                'user_id' => $user->id,
            ],
        ]);
    }

    public function registerTestDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'device_type' => ['required', 'in:ios,android'],
            'device_name' => ['nullable', 'string'],
        ]);

        $token = DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'device_type' => $validated['device_type'],
                'device_name' => $validated['device_name'] ?? null,
                'is_active' => true,
            ]
        );

        return response()->json([
            'data' => [
                'id' => $token->id,
                'token' => $token->token,
                'device_type' => $token->device_type,
                'status' => 'registered',
            ],
        ], 201);
    }

    public function listNotifications(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $notifications->map(fn (Notification $n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
            ]),
        ]);
    }
}
