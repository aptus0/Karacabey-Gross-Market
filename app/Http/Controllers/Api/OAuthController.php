<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\Auth\ApiTokenIssuer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class OAuthController
{
    /**
     * Redirect to OAuth provider
     */
    public function redirect(string $provider): mixed
    {
        if (!in_array($provider, ['google', 'github', 'facebook'])) {
            abort(404);
        }

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle OAuth callback
     */
    public function callback(string $provider, ApiTokenIssuer $tokenIssuer): JsonResponse
    {
        if (!in_array($provider, ['google', 'github', 'facebook'])) {
            abort(404);
        }

        try {
            $oauthUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'OAuth doğrulaması başarısız',
                'error' => $e->getMessage(),
            ], 401);
        }

        // Find or create user
        $oauthIdColumn = $provider . '_id';
        $oauthEmailColumn = $provider . '_email';

        $user = User::where($oauthIdColumn, $oauthUser->getId())
            ->orWhere('email', $oauthUser->getEmail())
            ->first();

        if ($user) {
            // Update OAuth credentials
            $user->update([
                $oauthIdColumn => $oauthUser->getId(),
                $oauthEmailColumn => $oauthUser->getEmail(),
            ]);
        } else {
            // Create new user
            $user = User::create([
                'name' => $oauthUser->getName() ?? $oauthUser->getNickname() ?? 'User',
                'email' => $oauthUser->getEmail(),
                'password' => Hash::make(Str::random(32)),
                $oauthIdColumn => $oauthUser->getId(),
                $oauthEmailColumn => $oauthUser->getEmail(),
                'email_verified_at' => now(),
            ]);
        }

        // Generate API token
        $tokenData = $tokenIssuer->issue($user, 'oauth-' . $provider);

        return response()->json([
            'message' => 'Başarıyla giriş yapıldı',
            'data' => [
                'token' => $tokenData['token'],
                'user' => $user,
            ],
        ]);
    }

    /**
     * Disconnect OAuth provider from authenticated user
     */
    public function disconnect(Request $request, string $provider): JsonResponse
    {
        if (!in_array($provider, ['google', 'github', 'facebook'])) {
            abort(404);
        }

        $user = $request->user();

        $oauthIdColumn = $provider . '_id';
        $oauthEmailColumn = $provider . '_email';

        if (!$user->$oauthIdColumn) {
            return response()->json([
                'message' => 'Bu sağlayıcı bağlı değil',
            ], 422);
        }

        $user->update([
            $oauthIdColumn => null,
            $oauthEmailColumn => null,
        ]);

        return response()->json([
            'message' => ucfirst($provider) . ' bağlantısı kaldırıldı',
        ]);
    }
}
