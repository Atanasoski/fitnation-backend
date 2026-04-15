<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workout_template_exercises', function (Blueprint $table) {
            $table->dropColumn('target_reps');
            $table->integer('min_target_reps')->default(8)->after('target_sets');
            $table->integer('max_target_reps')->default(12)->after('min_target_reps');
        });

        Schema::table('workout_session_exercises', function (Blueprint $table) {
            $table->dropColumn('target_reps');
            $table->integer('min_target_reps')->nullable()->after('target_sets');
            $table->integer('max_target_reps')->nullable()->after('min_target_reps');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workout_template_exercises', function (Blueprint $table) {
            $table->dropColumn(['min_target_reps', 'max_target_reps']);
            $table->integer('target_reps')->default(10)->after('target_sets');
        });

        Schema::table('workout_session_exercises', function (Blueprint $table) {
            $table->dropColumn(['min_target_reps', 'max_target_reps']);
            $table->integer('target_reps')->nullable()->after('target_sets');
        });
    }
};
