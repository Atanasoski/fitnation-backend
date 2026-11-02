<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Printed on QR stickers (ADR-0005): generated once, never
            // regenerated, never recycled — stations soft-delete only.
            $table->string('short_code', 16)->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('station_exercise', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained('workout_exercises')->cascadeOnDelete();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->unique(['station_id', 'exercise_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_exercise');
        Schema::dropIfExists('stations');
    }
};
