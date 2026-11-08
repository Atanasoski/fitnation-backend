<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('join_prompt_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('kind'); // declined | snoozed (JoinPromptDismissalKind)
            // Only set while kind = snoozed; a decline is permanent.
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamps();

            // One dismissal per customer×Partner, upserted on repeat.
            $table->unique(['user_id', 'partner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('join_prompt_dismissals');
    }
};
