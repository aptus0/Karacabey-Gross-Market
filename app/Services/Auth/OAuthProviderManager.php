<?php

namespace App\Services\Auth;

use App\Data\Auth\SocialUserData;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use League\OAuth2\Client\Provider\Facebook;
use League\OAuth2\Client\Provider\Google;
use RuntimeException;

class OAuthProviderManager
{
    /**
     * @return array<string, array{label: string, enabled: bool, redirect_url: ?string}>
     */
    public function statuses(): array
    {
        return collect(['google', 'facebook'])
            ->mapWithKeys(fn (string $provider): array => [
                $provider => [
                    'label' => $this->labelFor($provider),
                    'enabled' => $this->isEnabled($provider),
                    'redirect_url' => $this->isEnabled($provider) ? route('oauth.redirect', ['provider' => $provider]) : null,
                ],
            ])
            ->all();
    }

    public function isEnabled(string $provider): bool
    {
        $provider = $this->normalizeProvider($provider);
        $config = $this->configFor($provider);

        return filled($config['clientId'])
            && filled($config['clientSecret'])
            && filled($config['redirectUri']);
    }

    /**
     * @return array{url: string, state: ?string}
     */
    public function authorization(string $provider): array
    {
        $client = $this->client($provider);
        $url = $client->getAuthorizationUrl([
            'scope' => $this->scopesFor($provider),
        ]);

        return [
            'url' => $url,
            'state' => $client->getState(),
        ];
    }

    public function userFromCode(string $provider, string $code): SocialUserData
    {
        $provider = $this->normalizeProvider($provider);
        $client = $this->client($provider);
        $token = $client->getAccessToken('authorization_code', ['code' => $code]);
        $resourceOwner = $client->getResourceOwner($token);
        $data = $resourceOwner->toArray();

        return match ($provider) {
            'google' => new SocialUserData(
                provider: $provider,
                providerId: (string) $resourceOwner->getId(),
                email: $resourceOwner->getEmail(),
                name: $resourceOwner->getName() ?: $resourceOwner->getEmail() ?: 'Google Kullanicisi',
                avatarUrl: $resourceOwner->getAvatar() ?: Arr::get($data, 'picture'),
            ),
            'facebook' => new SocialUserData(
                provider: $provider,
                providerId: (string) $resourceOwner->getId(),
                email: $resourceOwner->getEmail(),
                name: $resourceOwner->getName() ?: $resourceOwner->getEmail() ?: 'Facebook Kullanicisi',
                avatarUrl: Arr::get($data, 'picture.url'),
            ),
            default => throw new InvalidArgumentException('Unsupported provider.'),
        };
    }

    public function labelFor(string $provider): string
    {
        return match ($this->normalizeProvider($provider)) {
            'google' => 'Google',
            'facebook' => 'Facebook',
            default => Str::headline($provider),
        };
    }

    private function client(string $provider): Google|Facebook
    {
        $provider = $this->normalizeProvider($provider);

        if (! $this->isEnabled($provider)) {
            throw new RuntimeException('OAuth provider is disabled.');
        }

        $config = $this->configFor($provider);

        return match ($provider) {
            'google' => new Google($config),
            'facebook' => new Facebook([
                ...$config,
                'graphApiVersion' => 'v23.0',
            ]),
            default => throw new InvalidArgumentException('Unsupported provider.'),
        };
    }

    /**
     * @return array{clientId: string, clientSecret: string, redirectUri: string}
     */
    private function configFor(string $provider): array
    {
        $provider = $this->normalizeProvider($provider);

        return match ($provider) {
            'google' => [
                'clientId' => (string) config('services.google.oauth_client_id'),
                'clientSecret' => (string) config('services.google.oauth_client_secret'),
                'redirectUri' => (string) config('services.google.oauth_redirect'),
            ],
            'facebook' => [
                'clientId' => (string) config('services.facebook.client_id'),
                'clientSecret' => (string) config('services.facebook.client_secret'),
                'redirectUri' => (string) config('services.facebook.redirect'),
            ],
            default => throw new InvalidArgumentException('Unsupported provider.'),
        };
    }

    /**
     * @return list<string>
     */
    private function scopesFor(string $provider): array
    {
        return match ($this->normalizeProvider($provider)) {
            'google' => ['openid', 'profile', 'email'],
            'facebook' => ['email', 'public_profile'],
            default => [],
        };
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = Str::lower(trim($provider));

        if (! in_array($provider, ['google', 'facebook'], true)) {
            throw new InvalidArgumentException('Unsupported provider.');
        }

        return $provider;
    }
}
