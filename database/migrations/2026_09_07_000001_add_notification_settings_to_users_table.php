<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-category notification preferences, as a JSON object of booleans —
     * {"weekly_summary_email": true} to begin with. Null means every
     * preference is at its default; only what the user has changed is written.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('notification_settings')->nullable()->after('push_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_settings');
        });
    }
};
