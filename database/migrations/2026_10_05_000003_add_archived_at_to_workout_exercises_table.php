<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An Archived Exercise is retired from searching, picking and generating but
 * still loads everywhere it was used (spec 023). Deliberately not SoftDeletes:
 * its global scope would make SetLog->exercise and template rows resolve to
 * null and break history. App\Services\Exercise\ExerciseArchive owns the rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
