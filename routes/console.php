<?php

use App\Jobs\FetchExpoReceipts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Push receipts: Expo holds the outcome of each push for a while; collect them
// and retire dead Devices. See docs/issues/018 and DEPLOY_LIGHTSAIL.md for the
// cron that drives this.
Schedule::job(new FetchExpoReceipts)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Inactivity Nudges: 18:00 local is a different UTC minute for every timezone,
// and some are offset by half an hour, so look every quarter hour. Running
// more than once in the same hour is harmless — the sent record dedupes.
Schedule::command('notifications:inactivity')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();
