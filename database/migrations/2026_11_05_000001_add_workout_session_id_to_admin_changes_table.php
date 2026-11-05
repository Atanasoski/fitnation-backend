<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A session cancel is an admin change too: the record points at the session
 * it cancelled. Null for every other kind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_changes', function (Blueprint $table) {
            $table->foreignId('workout_session_id')->nullable()->after('to_partner_id')->constrained('workout_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admin_changes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workout_session_id');
        });
    }
};
