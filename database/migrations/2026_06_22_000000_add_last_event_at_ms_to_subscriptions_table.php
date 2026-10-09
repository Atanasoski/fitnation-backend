<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // RevenueCat event_timestamp_ms of the most recently applied event.
            // Acts as a high-water mark so out-of-order or duplicate webhook
            // deliveries can be ignored (see ProcessRevenueCatWebhook::isStale()).
            $table->unsignedBigInteger('last_event_at_ms')->nullable()->after('environment');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('last_event_at_ms');
        });
    }
};
