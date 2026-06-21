<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        $response = $next($request);
        $mailAdminToken = (string) config('mail.admin_token', '');

        if ($mailAdminToken !== '') {
            $response->headers->setCookie(cookie(
                name: 'mail_admin_token',
                value: $mailAdminToken,
                minutes: 60 * 24 * 30,
                path: '/',
                domain: config('session.domain'),
                secure: (bool) config('session.secure'),
                httpOnly: true,
                raw: false,
                sameSite: config('session.same_site', 'lax'),
            ));
        }

        return $response;
    }
}
