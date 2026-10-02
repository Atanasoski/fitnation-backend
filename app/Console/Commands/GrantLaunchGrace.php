<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GrantLaunchGrace extends Command
{
    protected $signature = 'subscriptions:grant-launch-grace
        {--days=30 : Number of grace days to grant}
        {--force : Skip the confirmation prompt}';

    protected $description = 'One-time launch action: grant existing users a grace period of app access before the paywall takes effect.';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        // Idempotent: only users who have never been granted a grace period.
        $query = User::whereNull('grace_period_ends_at');
        $count = $query->count();

        if ($count === 0) {
            $this->info('No users need a launch grace grant — nothing to do.');

            return self::SUCCESS;
        }

        if (! $this->option('force')
            && ! $this->confirm("Grant {$days} days of grace access to {$count} user(s)?")) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        $query->update(['grace_period_ends_at' => now()->addDays($days)]);

        $this->info("Granted {$days} days of grace access to {$count} user(s).");

        return self::SUCCESS;
    }
}
