<?php

namespace App\Console\Commands;

use App\Notifications\WeeklySummary;
use App\Services\Notifications\WeeklySummaries;
use App\Services\Notifications\WeeklySummaryCandidate;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Send the Weekly Summaries that are due right now. Thin on purpose: who is due
 * is App\Services\Notifications\WeeklySummaries's answer, and running this
 * twice in the same hour sends nothing the second time because the sent
 * record is part of that answer. Cheap on six days out of seven — the first
 * read is "which timezones are at Monday 08:00", and the answer is none.
 *
 * The notification is queued, so a Monday-morning burst for one timezone
 * leaves here as jobs rather than as mail sent inline.
 */
class SendWeeklySummaries extends Command
{
    protected $signature = 'notifications:weekly-summaries';

    protected $description = 'Email last week\'s Weekly Summary to the users whose Monday 08:00 it is';

    public function handle(): int
    {
        $due = WeeklySummaries::dueAt(CarbonImmutable::now());

        $due->each(function (WeeklySummaryCandidate $candidate) {
            $candidate->user->notify(new WeeklySummary($candidate->progress()));
        });

        $this->info("Weekly Summaries sent: {$due->count()}");

        return self::SUCCESS;
    }
}
