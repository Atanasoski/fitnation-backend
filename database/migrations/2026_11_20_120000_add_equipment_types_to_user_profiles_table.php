<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The member's Equipment Selection, stored on their profile (#154). A json
 * list of EquipmentType codes; null means Full gym (ADR-0014's empty
 * selection). No backfill: existing members stay null, so no Program changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->json('equipment_types')->nullable()->after('workout_duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('equipment_types');
        });
    }
};
