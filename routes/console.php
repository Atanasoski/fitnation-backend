<?php

use App\Jobs\FetchExpoReceipts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Everything scheduled here runs at :00 and :30. The App cluster scales to zero
// (Laravel Cloud) and a due task wakes it for the sleep timeout, so every tick
// is paid compute: two ticks an hour reach every timezone — each local hour
// contains one — and leave one retry for a missed tick, at a third of the
// wakes of a quarter-hour clock.
//
// Push receipts: Expo holds the outcome of each push for a day; collect them
// and retire dead Devices. See docs/issues/018 and DEPLOY_LIGHTSAIL.md for the
// cron that drives this.
Schedule::job(new FetchExpoReceipts)
    ->hourlyAt([0, 30])
    ->withoutOverlapping()
    ->onOneServer();

// Inactivity Nudges: 18:00 local is a different UTC minute for every timezone.
// A user is due for the whole of their hour, and running more than once in it
// is harmless — the Sent Record dedupes.
Schedule::command('notifications:inactivity')
    ->hourlyAt([0, 30])
    ->withoutOverlapping()
    ->onOneServer();

// Unfinished Account nudges: 10:00 local — the home timezone for the many who
// have no Device yet — on the same clock, deduped the same way.
Schedule::command('notifications:unfinished-accounts')
    ->hourlyAt([0, 30])
    ->withoutOverlapping()
    ->onOneServer();

// Weekly Summaries: Monday 08:00 local, on the same clock and deduped the same
// way. Six days a week the first read finds no timezone at Monday 08:00 and the
// command returns at once.
Schedule::command('notifications:weekly-summaries')
    ->hourlyAt([0, 30])
    ->withoutOverlapping()
    ->onOneServer();
