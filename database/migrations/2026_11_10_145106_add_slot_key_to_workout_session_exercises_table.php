<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Blueprint slot a generated row came from (issue #107); null for
     * rows outside generation (manual templates, user-added exercises).
     */
    public function up(): void
    {
        Schema::table('workout_session_exercises', function (Blueprint $table) {
            $table->string('slot_key')->nullable()->after('rest_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('workout_session_exercises', function (Blueprint $table) {
            $table->dropColumn('slot_key');
        });
    }
};
