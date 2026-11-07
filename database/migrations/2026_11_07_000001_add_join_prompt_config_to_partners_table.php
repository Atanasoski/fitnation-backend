<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            // Per-partner rollout switch for the Join Prompt: off by default,
            // and only enableable once the partner has at least one Gym
            // Location and one benefit (issue #86).
            $table->boolean('geo_prompt_enabled')->default(false);
            // What the Join Prompt lists for this gym, one string per line in
            // the admin form.
            $table->json('benefits')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn(['geo_prompt_enabled', 'benefits']);
        });
    }
};
