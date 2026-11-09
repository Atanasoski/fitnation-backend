<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deload sessions are not performance history (ADR-0008). The template flag is
 * the source of truth, written by the program generator; the session flag is
 * copied from the template at session start so progression lookups can filter
 * without a join.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_templates', function (Blueprint $table) {
            $table->boolean('is_deload')->default(false)->after('week_number');
        });

        Schema::table('workout_sessions', function (Blueprint $table) {
            $table->boolean('is_deload')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('workout_templates', function (Blueprint $table) {
            $table->dropColumn('is_deload');
        });

        Schema::table('workout_sessions', function (Blueprint $table) {
            $table->dropColumn('is_deload');
        });
    }
};
