<?php

namespace App\Console\Commands;

use App\Notifications\UnfinishedAccountNudge;
use App\Services\Notifications\UnfinishedAccountCandidate;
use App\Services\Notifications\UnfinishedAccounts;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Send the Unfinished Account nudges that are due right now. Thin on purpose:
 * who is due is App\Services\Notifications\UnfinishedAccounts's answer, and
 * running this twice in the same hour sends nothing the second time because
 * the sent record is part of that answer.
 */
class SendUnfinishedAccountNudges extends Command
{
    protected $signature = 'notifications:unfinished-accounts';

    protected $description = 'Email the Unfinished Accounts whose nudge is due at this hour in their users\' timezones';

    public function handle(): int
    {
        $due = UnfinishedAccounts::dueAt(CarbonImmutable::now());

        $due->each(function (UnfinishedAccountCandidate $candidate) {
            $candidate->user->notify(new UnfinishedAccountNudge($candidate->step, $candidate->stuckAt));
        });

        $this->info("Unfinished Account nudges sent: {$due->count()}");

        return self::SUCCESS;
    }
}
