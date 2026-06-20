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

        // Backfill: grant 30 days of grace access to all existing users at launch.
        \DB::table('users')->update([
            'grace_period_ends_at' => now()->addDays(30),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('grace_period_ends_at');
        });
    }
};
