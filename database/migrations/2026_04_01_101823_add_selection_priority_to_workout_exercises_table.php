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
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->unsignedSmallInteger('selection_priority')->default(100)->after('difficulty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->dropColumn('selection_priority');
        });
    }
};
