<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('grace_period_ends_at')->nullable();
        });

        // NOTE: the one-time launch grace grant is intentionally NOT done here —
        // run `php artisan subscriptions:grant-launch-grace` explicitly at launch
        // so a bulk business action isn't an irreversible migration side-effect.
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('grace_period_ends_at');
        });
    }
};
