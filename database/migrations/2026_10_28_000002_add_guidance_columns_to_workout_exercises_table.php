<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table) {
            // Structured guidance for the public QR pages: ordered step
            // strings and mistake strings. Global (not partner-overridable);
            // null until the AI backfill command fills them, and clients fall
            // back to `description` while null.
            $table->json('instructions')->nullable()->after('description');
            $table->json('common_mistakes')->nullable()->after('instructions');
        });
    }

    public function down(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->dropColumn(['instructions', 'common_mistakes']);
        });
    }
};
