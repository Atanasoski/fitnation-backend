<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Spatie\WebhookClient\Models\WebhookCall;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Prune stored webhook payloads past their retention window. The webhook-client
// config declares delete_after_days but ships no scheduled pruner, so wire one.
Schedule::call(function () {
    $days = (int) config('webhook-client.delete_after_days', 90);

    WebhookCall::where('created_at', '<', now()->subDays($days))->delete();
})->daily()->name('prune-webhook-calls');
