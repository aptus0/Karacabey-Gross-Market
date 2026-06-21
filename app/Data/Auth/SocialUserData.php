<?php

namespace App\Data\Auth;

final readonly class SocialUserData
{
    public function __construct(
        public string $provider,
        public string $providerId,
        public ?string $email,
        public string $name,
        public ?string $avatarUrl = null,
    ) {}
}
