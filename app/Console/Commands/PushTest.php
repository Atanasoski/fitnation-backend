<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\TestPush;
use Illuminate\Console\Command;

/**
 * For a human holding a phone: push something to one user's Devices right now
 * and show the tickets Expo handed back. Not scheduled, not for automation.
 */
class PushTest extends Command
{
    protected $signature = 'push:test
        {user : The user\'s id or email}
        {--title=Test push : Notification title}
        {--body=If you can read this, the pipe works. : Notification body}';

    protected $description = 'Send an ad-hoc push to every Device of one user and print the Expo tickets';

    public function handle(): int
    {
        $lookup = (string) $this->argument('user');

        $user = is_numeric($lookup)
            ? User::find((int) $lookup)
            : User::query()->where('email', $lookup)->first();

        if ($user === null) {
            $this->error("No user matches '{$lookup}'.");

            return self::FAILURE;
        }

        $devices = $user->devices()->count();

        if ($devices === 0) {
            $this->error("{$user->email} has no Devices — the app has not registered on any phone for this account.");

            return self::FAILURE;
        }

        if (! config('notifications.enabled')) {
            $this->warn('NOTIFICATIONS_ENABLED is off: the push will be logged, not sent.');
        }

        $notification = new TestPush((string) $this->option('title'), (string) $this->option('body'));
        $user->notify($notification);

        $tickets = $user->notifications()->find($notification->id)?->data['expo_tickets'] ?? [];

        $this->info("Pushed to {$user->email}: {$devices} Device(s), ".count($tickets).' ticket(s).');

        if ($tickets !== []) {
            $this->table(['Device', 'Expo ticket'], collect($tickets)->map(
                fn (string $ticket, int $deviceId) => [$deviceId, $ticket]
            )->values()->all());
            $this->line('Receipts arrive in ~15 minutes; a dead token will remove its Device then.');
        }

        return self::SUCCESS;
    }
}
