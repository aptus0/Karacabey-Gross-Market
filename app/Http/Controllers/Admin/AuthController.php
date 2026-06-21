<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Security\AdminAccessInspector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('admin.auth.login');
    }

    public function login(Request $request, AdminAccessInspector $inspector): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $attempted = Auth::attempt($credentials + ['is_admin' => true], $request->boolean('remember'));
        $inspector->recordLoginAttempt($request, (string) $credentials['email'], $attempted);

        if (! $attempted) {
            throw ValidationException::withMessages([
                'email' => 'Admin giriş bilgileri hatalı veya yetkisiz erişim.',
            ]);
        }

        $request->session()->regenerate();
        $request->user()?->forceFill([
            'last_ip' => $request->ip(),
            'last_login_at' => now(),
        ])->save();

        return redirect()
            ->intended(route('admin.dashboard'))
            ->withCookie($this->mailAdminCookie());
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('admin.login')
            ->withCookie(Cookie::forget('mail_admin_token', '/', config('session.domain')));
    }

    private function mailAdminCookie()
    {
        $token = (string) config('mail.admin_token', '');

        if ($token === '') {
            return Cookie::forget('mail_admin_token', '/', config('session.domain'));
        }

        return cookie(
            name: 'mail_admin_token',
            value: $token,
            minutes: 60 * 24 * 30,
            path: '/',
            domain: config('session.domain'),
            secure: (bool) config('session.secure'),
            httpOnly: true,
            raw: false,
            sameSite: config('session.same_site', 'lax'),
        );
    }
}
