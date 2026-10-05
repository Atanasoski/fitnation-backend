<?php

namespace App\Console\Commands;

use App\Webhooks\RevenueCat\FailedWebhookCalls;
use Illuminate\Console\Command;

class ReplayFailedRevenueCatWebhooks extends Command
{
    protected $signature = 'revenuecat:replay-failed
        {--since=7 : Only replay calls created within this many days}
        {--id=* : Replay specific webhook_call ids (ignores --since)}
        {--all : Replay every failed call regardless of age}';

    protected $description = 'Re-dispatch RevenueCat webhook calls that previously failed (e.g. the user was not yet registered when the event first arrived).';

    public function handle(): int
    {
        $ids = $this->option('id');

        $query = FailedWebhookCalls::query();

        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        } elseif (! $this->option('all')) {
            $query->where('created_at', '>=', now()->subDays((int) $this->option('since')));
        }

        $calls = $query->get();

        if ($calls->isEmpty()) {
            $this->info('No failed RevenueCat webhook calls to replay.');

            return self::SUCCESS;
        }

        $this->info("Replaying {$calls->count()} failed RevenueCat webhook call(s)...");

        foreach ($calls as $call) {
            FailedWebhookCalls::replay($call);
            $this->line("  • dispatched webhook_call #{$call->id}");
        }

        $this->info('Done. Watch the queue/logs for results.');

        return self::SUCCESS;
    }
}
