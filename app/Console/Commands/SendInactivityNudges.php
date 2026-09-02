<?php

namespace App\Console\Commands;

use App\Notifications\InactivityNudge;
use App\Services\Notifications\Inactivity;
use App\Services\Notifications\InactivityNudgeCandidate;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Send the Inactivity Nudges that are due right now. Thin on purpose: who is
 * due is App\Services\Notifications\Inactivity's answer, and running this twice
 * in the same hour sends nothing the second time because the sent record is
 * part of that answer.
 */
class SendInactivityNudges extends Command
{
    protected $signature = 'notifications:inactivity';

    protected $description = 'Send the Inactivity Nudges that are due at this hour in their users\' timezones';

    public function handle(): int
    {
        $due = Inactivity::dueAt(CarbonImmutable::now());

        $due->each(function (InactivityNudgeCandidate $candidate) {
            $candidate->user->notify(new InactivityNudge($candidate->step));
        });

        $this->info("Inactivity nudges sent: {$due->count()}");

        return self::SUCCESS;
    }
}
