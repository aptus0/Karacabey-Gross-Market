<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MailAccessController
{
    public function __invoke(Request $request): Response
    {
        $mailAdminToken = (string) config('mail.admin_token', '');
        $providedToken = (string) ($request->header('X-Mail-Admin-Token') ?: $request->cookie('mail_admin_token', ''));

        // 1) Dış API çağrısı — sabit env token ile.
        if ($mailAdminToken !== '' && $providedToken !== '' && hash_equals($mailAdminToken, $providedToken)) {
            return response('', 204)
                ->header('Cache-Control', 'no-store')
                ->header('X-KGM-Mail-Admin-Token', $mailAdminToken);
        }

        $user = $request->user();

        if (! $user) {
            return response('Unauthorized', 401)
                ->header('Cache-Control', 'no-store');
        }

        if (! $user->is_admin) {
            return response('Forbidden', 403)
                ->header('Cache-Control', 'no-store');
        }

        $response = response('', 204)
            ->header('Cache-Control', 'no-store')
            ->header('X-KGM-Mail-Admin', (string) $user->getAuthIdentifier());

        if ($mailAdminToken !== '') {
            $response->header('X-KGM-Mail-Admin-Token', $mailAdminToken);
        }

        return $response;
    }
}
