<?php

namespace App\Http\Middleware;

use App\Services\Security\AdminAccessInspector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InspectAdminAccess
{
    public function __construct(private readonly AdminAccessInspector $inspector) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_admin) {
            return $next($request);
        }

        $inspection = $this->inspector->inspect($request);

        if ($inspection['should_block']) {
            $log = $this->inspector->recordBlockedRequest($request, $inspection);

            return response()->view('admin.auth.fake-auth2', [
                'mode' => 'blocked',
                'challengeId' => 'KGM-'.str_pad((string) $log->id, 6, '0', STR_PAD_LEFT),
                'clientId' => 'kgm-admin-console',
                'scope' => 'admin.access security.audit',
            ], 423);
        }

        return $next($request);
    }
}
