<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per Scan, exercise open or CTA click on a Station's public
        // page, with the app benefit that was shown. Deliberately anonymous:
        // no user, session or device identification of any kind.
        Schema::create('station_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->nullable()->constrained('workout_exercises')->nullOnDelete();
            $table->string('type', 32);
            $table->string('benefit', 100)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['station_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_events');
    }
};
