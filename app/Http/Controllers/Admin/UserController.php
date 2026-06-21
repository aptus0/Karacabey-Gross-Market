<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->withCount('orders')->latest()->paginate(20),
        ]);
    }

    public function updateVip(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is_admin, 422, 'Yönetici hesaplarında VIP müşteri modu değiştirilemez.');

        $validated = $request->validate([
            'is_vip' => ['nullable', 'boolean'],
            'vip_expires_at' => ['nullable', 'date', 'after:now'],
            'vip_note' => ['nullable', 'string', 'max:255'],
        ]);

        $enabled = (bool) ($validated['is_vip'] ?? false);

        $user->forceFill([
            'is_vip' => $enabled,
            'vip_started_at' => $enabled && ! $user->vip_started_at ? now() : ($enabled ? $user->vip_started_at : null),
            'vip_expires_at' => $enabled ? ($validated['vip_expires_at'] ?? null) : null,
            'vip_note' => $enabled ? ($validated['vip_note'] ?? null) : null,
            'sync_version' => (int) floor(microtime(true) * 1_000_000),
        ])->save();

        return redirect()
            ->route('admin.users.index')
            ->with('status', $enabled ? 'VIP müşteri modu aktif edildi.' : 'VIP müşteri modu kapatıldı.');
    }
}
