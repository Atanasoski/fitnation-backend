<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invitations are gone end to end (spec 025 ticket 03). `down()` recreates
 * the table exactly as 2025_12_26_233454_create_user_invitations_table did;
 * the rows are not restored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_invitations');
    }

    public function down(): void
    {
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('token')->unique();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['partner_id', 'email']);
            $table->index('token');
        });
    }
};
