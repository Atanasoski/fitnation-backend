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
