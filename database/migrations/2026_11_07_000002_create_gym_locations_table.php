<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            // 7 decimal places is ~1 cm; plenty for a gym door.
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_m')->default(150);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_locations');
    }
};
