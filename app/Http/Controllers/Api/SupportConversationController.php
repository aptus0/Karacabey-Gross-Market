<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\Support\SupportAiResponder;
use App\Support\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SupportConversationController extends Controller
{
    public function store(Request $request, TenantResolver $tenants, SupportAiResponder $ai): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:1200'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:160'],
            'guest_token' => ['nullable', 'string', 'max:120'],
            'metadata' => ['nullable', 'array'],
        ]);

        $tenant = $tenants->resolve($request);
        $user = Auth::guard('api')->user();
        $guestToken = $validated['guest_token'] ?? $request->header('X-Customer-UID') ?? Str::uuid()->toString();

        $conversation = SupportConversation::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user?->id,
            'public_token' => Str::random(48),
            'guest_token' => $guestToken,
            'status' => SupportConversation::STATUS_OPEN,
            'source' => 'web',
            'customer_name' => $validated['name'] ?? $user?->name,
            'customer_email' => $validated['email'] ?? $user?->email,
            'customer_phone' => $validated['phone'] ?? $user?->phone,
            'subject' => $validated['subject'] ?? 'Müşteri desteği',
            'metadata' => array_merge($validated['metadata'] ?? [], [
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 400, ''),
            ]),
        ]);

        $message = $this->createCustomerMessage($conversation, $validated['message'], $user?->id);
        $ai->maybeReply($conversation, $message);

        return response()->json([
            'data' => $this->serializeConversation($conversation->fresh(['messages'])),
        ], 201);
    }

    public function messages(Request $request, SupportConversation $conversation): JsonResponse
    {
        $this->assertPublicAccess($request, $conversation);

        return response()->json([
            'data' => [
                'conversation' => $this->serializeConversation($conversation),
                'messages' => $conversation->messages()->get()->map(fn (SupportMessage $message): array => $this->serializeMessage($message))->values(),
            ],
        ]);
    }

    public function message(Request $request, SupportConversation $conversation, SupportAiResponder $ai): JsonResponse
    {
        $this->assertPublicAccess($request, $conversation);

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:1200'],
        ]);

        $message = $this->createCustomerMessage($conversation, $validated['message'], Auth::guard('api')->id());
        $ai->maybeReply($conversation->fresh('messages'), $message);

        return response()->json([
            'data' => [
                'conversation' => $this->serializeConversation($conversation->fresh()),
                'message' => $this->serializeMessage($message),
            ],
        ]);
    }

    public function stream(Request $request, SupportConversation $conversation): StreamedResponse
    {
        $this->assertPublicAccess($request, $conversation);

        return $this->messageStream($conversation, (int) $request->query('after_id', 0));
    }

    private function createCustomerMessage(SupportConversation $conversation, string $body, ?int $userId): SupportMessage
    {
        $message = $conversation->messages()->create([
            'user_id' => $userId,
            'sender_type' => SupportMessage::SENDER_CUSTOMER,
            'sender_name' => $conversation->customer_name ?: 'Müşteri',
            'body' => trim($body),
        ]);

        $conversation->forceFill(['status' => SupportConversation::STATUS_OPEN])->save();
        $conversation->touchLastMessage($message);

        return $message;
    }

    private function assertPublicAccess(Request $request, SupportConversation $conversation): void
    {
        $token = (string) ($request->query('token') ?: $request->input('token') ?: $request->header('X-Support-Token'));
        $user = Auth::guard('api')->user();

        if ($token !== '' && hash_equals($conversation->public_token, $token)) {
            return;
        }

        if ($user && $conversation->user_id === $user->id) {
            return;
        }

        throw new NotFoundHttpException;
    }

    private function messageStream(SupportConversation $conversation, int $afterId): StreamedResponse
    {
        return response()->stream(function () use ($conversation, $afterId): void {
            $lastId = $afterId;
            $deadline = now()->addSeconds(28);

            while (now()->lessThan($deadline)) {
                $messages = $conversation->messages()
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->get();

                foreach ($messages as $message) {
                    $lastId = $message->id;
                    echo "event: message\n";
                    echo 'data: '.json_encode($this->serializeMessage($message), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
                }

                echo "event: heartbeat\n";
                echo 'data: {"ok":true}'."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                sleep(2);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function serializeConversation(?SupportConversation $conversation): array
    {
        if (! $conversation) {
            return [];
        }

        return [
            'id' => $conversation->id,
            'token' => $conversation->public_token,
            'status' => $conversation->status,
            'subject' => $conversation->subject,
            'customer_name' => $conversation->customer_name,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
        ];
    }

    private function serializeMessage(SupportMessage $message): array
    {
        return [
            'id' => $message->id,
            'sender_type' => $message->sender_type,
            'sender_name' => $message->sender_name,
            'body' => $message->body,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
