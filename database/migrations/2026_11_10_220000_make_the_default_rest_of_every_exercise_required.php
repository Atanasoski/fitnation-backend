<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every Exercise has a Default Rest (GLOSSARY): the generator scales it by
 * the member's goal for every row without logged rest, and the goal-default
 * fallback for a missing one is gone (#128, ADR-0010).
 *
 * The production copy had 0 of 307 exercises without one on 2026-11-10.
 * As a guard, any exercise still without one gets 90s first, the default
 * the admin form and the importer already use; then the column stops
 * accepting NULL, defaulting to 90s for inserts that leave it out.
 */
return new class extends Migration
{
    private const FALLBACK_REST_SECONDS = 90;

    public function up(): void
    {
        DB::table('workout_exercises')
            ->whereNull('default_rest_sec')
            ->update(['default_rest_sec' => self::FALLBACK_REST_SECONDS]);

        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->integer('default_rest_sec')->nullable(false)->default(self::FALLBACK_REST_SECONDS)->change();
        });
    }

    public function down(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->integer('default_rest_sec')->nullable()->default(null)->change();
        });
    }
};
