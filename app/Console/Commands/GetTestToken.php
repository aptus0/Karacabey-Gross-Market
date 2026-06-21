<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Auth\ApiTokenIssuer;
use Illuminate\Console\Command;

class GetTestToken extends Command
{
    protected $signature = 'test:token
        {--user-id=3}
        {--allow-production : Allow this command to run in production}
        {--confirm= : Required production confirmation phrase}';
    protected $description = 'Get or create API token for testing';

    public function handle(): int
    {
        if (app()->environment('production')
            && (! $this->option('allow-production') || $this->option('confirm') !== 'PRINT_PRODUCTION_TOKEN')) {
            $this->error('Production blocked. Re-run only with --allow-production --confirm=PRINT_PRODUCTION_TOKEN after manual approval.');

            return self::FAILURE;
        }

        $userId = $this->option('user-id');
        $user = User::find($userId);

        if (!$user) {
            $this->error("User {$userId} not found");
            return self::FAILURE;
        }

        $issuer = app(ApiTokenIssuer::class);
        $result = $issuer->issue($user, 'cli-test');

        $this->info("API Token for {$user->name}:");
        $this->line($result['token']);

        return self::SUCCESS;
    }
}
