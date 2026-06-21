<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;

class TestNotification extends Command
{
    protected $signature = 'test:notification
        {--user-id=1}
        {--allow-production : Allow this command to run in production}
        {--confirm= : Required production confirmation phrase}';
    protected $description = 'Send a test notification to a user';

    public function handle(): int
    {
        if (app()->environment('production')
            && (! $this->option('allow-production') || $this->option('confirm') !== 'SEND_PRODUCTION_TEST_NOTIFICATION')) {
            $this->error('Production blocked. Re-run only with --allow-production --confirm=SEND_PRODUCTION_TEST_NOTIFICATION after manual approval.');

            return self::FAILURE;
        }

        $userId = $this->option('user-id');
        $user = User::find($userId);

        if (!$user) {
            $this->error("User {$userId} not found");
            return self::FAILURE;
        }

        $this->info("Sending test notification to {$user->name}...");

        $service = new PushNotificationService();
        $service->sendToUser($user, 'Test Notification', 'This is a test message', [
            'type' => 'test',
            'message' => 'Testing the notification system',
        ]);

        // Check if notification was created
        $notification = Notification::where('user_id', $userId)->latest()->first();

        if ($notification) {
            $this->info("✓ Notification created successfully");
            $this->table(
                ['ID', 'Type', 'Title', 'Created At'],
                [
                    [$notification->id, $notification->type, $notification->title, $notification->created_at],
                ]
            );
        } else {
            $this->error("✗ Failed to create notification");
            return self::FAILURE;
        }

        // Check device tokens
        $tokens = DeviceToken::where('user_id', $userId)->get();
        if ($tokens->isEmpty()) {
            $this->warn("⚠ No device tokens registered for this user");
        } else {
            $this->info("✓ Found {$tokens->count()} device token(s)");
            $this->table(
                ['ID', 'Device Type', 'Device Name', 'Is Active'],
                $tokens->map(fn ($t) => [$t->id, $t->device_type, $t->device_name, $t->is_active ? 'Yes' : 'No'])->toArray()
            );
        }

        return self::SUCCESS;
    }
}
