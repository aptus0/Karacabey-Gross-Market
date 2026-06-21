<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Security\AdminAccessInspector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FakeAuth2Controller extends Controller
{
    public function __construct(private readonly AdminAccessInspector $inspector) {}

    public function show(Request $request): Response
    {
        $log = $this->inspector->recordDecoy($request, 'decoy_oauth_view', [
            'query' => $request->query(),
        ]);

        return response()->view('admin.auth.fake-auth2', [
            'mode' => 'challenge',
            'challengeId' => 'KGM-'.str_pad((string) $log->id, 6, '0', STR_PAD_LEFT),
            'clientId' => (string) $request->query('client_id', 'kgm-admin-console'),
            'scope' => (string) $request->query('scope', 'admin.access security.audit'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['nullable', 'string', 'max:191'],
            'client_id' => ['nullable', 'string', 'max:120'],
            'scope' => ['nullable', 'string', 'max:180'],
        ]);

        $this->inspector->recordDecoy($request, 'decoy_oauth_submit', [
            'identifier' => $data['identifier'] ?? null,
            'client_id' => $data['client_id'] ?? 'kgm-admin-console',
            'scope' => $data['scope'] ?? 'admin.access security.audit',
        ]);

        return back()
            ->withErrors(['identifier' => 'Auth2 dogrulama servisi bu istemci icin islemi tamamlayamadi.'])
            ->withInput($request->only('identifier', 'client_id', 'scope'));
    }

    public function trap(Request $request): Response
    {
        $log = $this->inspector->recordDecoy($request, 'decoy_admin_route', [
            'query' => $request->query(),
            'payload_keys' => array_keys($request->except(['password', 'password_confirmation', '_token'])),
        ]);

        return response()->view('admin.auth.fake-auth2', [
            'mode' => 'challenge',
            'challengeId' => 'KGM-'.str_pad((string) $log->id, 6, '0', STR_PAD_LEFT),
            'clientId' => 'kgm-admin-console',
            'scope' => 'admin.access security.audit',
        ]);
    }
}
