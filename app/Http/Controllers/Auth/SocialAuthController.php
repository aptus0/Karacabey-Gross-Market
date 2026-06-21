<?php

namespace App\Http\Controllers\Auth;

use App\Data\Auth\SocialUserData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\ApiTokenIssuer;
use App\Services\Auth\OAuthProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

class SocialAuthController extends Controller
{
    public function redirect(Request $request, OAuthProviderManager $providers, string $provider): RedirectResponse
    {
        abort_unless($providers->isEnabled($provider), 404);

        $authorization = $providers->authorization($provider);
        $request->session()->put($this->stateSessionKey($provider), $authorization['state']);

        return redirect()->away($authorization['url']);
    }

    public function callback(
        Request $request,
        OAuthProviderManager $providers,
        ApiTokenIssuer $tokenIssuer,
        string $provider
    ): RedirectResponse {
        if ($request->filled('error')) {
            return $this->redirectToStorefront([
                'provider' => $provider,
                'error' => (string) $request->query('error'),
                'message' => (string) $request->query('error_description', 'Sosyal giris iptal edildi.'),
            ]);
        }

        abort_unless($providers->isEnabled($provider), 404);

        $expectedState = (string) $request->session()->pull($this->stateSessionKey($provider), '');
        $providedState = (string) $request->query('state', '');
        abort_if($expectedState === '' || $providedState === '' || ! hash_equals($expectedState, $providedState), 422, 'OAuth state mismatch.');

        $code = (string) $request->query('code', '');
        abort_if($code === '', 422, 'OAuth code missing.');

        try {
            $socialUser = $providers->userFromCode($provider, $code);
            $user = $this->resolveUser($socialUser);
            $token = $tokenIssuer->issue($user, 'oauth-'.$provider);
        } catch (Throwable $exception) {
            return $this->redirectToStorefront([
                'provider' => $provider,
                'error' => 'oauth_failed',
                'message' => $exception->getMessage() ?: 'Sosyal giris tamamlanamadi.',
            ]);
        }

        return $this->redirectToStorefront([
            'provider' => $provider,
            'token' => $token,
        ]);
    }

    private function resolveUser(SocialUserData $socialUser): User
    {
        abort_if(blank($socialUser->email), 422, 'Saglayicidan e-posta bilgisi alinamadi.');

        $providerColumn = $socialUser->provider.'_id';

        $user = User::query()
            ->where($providerColumn, $socialUser->providerId)
            ->first();

        if (! $user) {
            $user = User::query()
                ->where('email', $socialUser->email)
                ->first();
        }

        if (! $user) {
            return User::query()->create([
                'name' => $socialUser->name,
                'email' => $socialUser->email,
                'password' => Hash::make(Str::password(24)),
                'email_verified_at' => Carbon::now(),
                $providerColumn => $socialUser->providerId,
                'avatar_url' => $socialUser->avatarUrl,
            ]);
        }

        $updates = [
            $providerColumn => $socialUser->providerId,
            'avatar_url' => $socialUser->avatarUrl ?: $user->avatar_url,
        ];

        if (! $user->email_verified_at) {
            $updates['email_verified_at'] = Carbon::now();
        }

        $user->fill($updates)->save();

        return $user->fresh();
    }

    /**
     * @param  array<string, string>  $values
     */
    private function redirectToStorefront(array $values): RedirectResponse
    {
        $baseUrl = rtrim((string) config('services.storefront.url', config('app.url')), '/');
        $hash = http_build_query($values);

        return redirect()->away($baseUrl.'/auth/callback#'.$hash);
    }

    private function stateSessionKey(string $provider): string
    {
        return 'oauth_state_'.Str::lower($provider);
    }
}
