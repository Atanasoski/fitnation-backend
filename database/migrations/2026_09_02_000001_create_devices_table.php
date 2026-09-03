<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A Device is an authenticated session (ADR-0003): one row per Sanctum
     * token that has registered a push token, gone when the token is.
     *
     * user_id is denormalised from the token's tokenable_id so the scheduler
     * can reach a user's Devices without joining through a polymorphic column.
     * DeviceRegistration is the only writer and keeps the two consistent.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('personal_access_token_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('push_token')->unique();
            $table->string('platform', 10);
            $table->string('timezone', 64)->nullable();
            $table->string('app_version', 32)->nullable();
            $table->string('build_profile', 32)->nullable();
            $table->string('device_name')->nullable();
            $table->timestamp('last_seen_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
