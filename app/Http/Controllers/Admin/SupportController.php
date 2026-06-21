<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Support\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function index(Request $request, TenantResolver $tenants): View
    {
        $tenant = $tenants->resolve($request);
        $status = (string) $request->input('status', 'open');

        $conversations = SupportConversation::query()
            ->where('tenant_id', $tenant->id)
            ->with(['assignedAdmin', 'messages' => fn ($query) => $query->latest('id')->limit(1)])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $selected = null;
        if ($request->filled('conversation')) {
            $selected = SupportConversation::query()
                ->where('tenant_id', $tenant->id)
                ->with(['messages', 'assignedAdmin', 'user'])
                ->find($request->integer('conversation'));
        }

        $selected ??= (clone $conversations->getCollection())->first();
        if ($selected && ! $selected->relationLoaded('messages')) {
            $selected->load(['messages', 'assignedAdmin', 'user']);
        }

        return view('admin.support.index', [
            'conversations' => $conversations,
            'selected' => $selected,
            'status' => $status,
            'statuses' => [
                SupportConversation::STATUS_OPEN => 'Açık',
                SupportConversation::STATUS_PENDING => 'Beklemede',
                SupportConversation::STATUS_CLOSED => 'Kapalı',
                'all' => 'Tümü',
            ],
        ]);
    }

    public function message(Request $request, SupportConversation $conversation): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:1500'],
        ]);

        $message = $conversation->messages()->create([
            'user_id' => $request->user()?->id,
            'sender_type' => SupportMessage::SENDER_ADMIN,
            'sender_name' => $request->user()?->name ?? 'Admin',
            'body' => trim($validated['message']),
        ]);

        $conversation->forceFill([
            'assigned_admin_id' => $conversation->assigned_admin_id ?: $request->user()?->id,
            'status' => SupportConversation::STATUS_PENDING,
        ])->save();
        $conversation->touchLastMessage($message);

        return back()->with('status', 'Yanıt gönderildi.');
    }

    public function update(Request $request, SupportConversation $conversation): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:open,pending,closed'],
        ]);

        $conversation->forceFill([
            'status' => $validated['status'],
            'assigned_admin_id' => $conversation->assigned_admin_id ?: $request->user()?->id,
        ])->save();

        return back()->with('status', 'Destek konuşması güncellendi.');
    }

    public function stream(Request $request, SupportConversation $conversation): StreamedResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $afterId = (int) $request->query('after_id', 0);

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
                    echo 'data: '.json_encode([
                        'id' => $message->id,
                        'sender_type' => $message->sender_type,
                        'sender_name' => $message->sender_name,
                        'body' => $message->body,
                        'created_at' => $message->created_at?->toIso8601String(),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
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
}
