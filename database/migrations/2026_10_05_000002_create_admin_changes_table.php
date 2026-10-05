<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The admin change record: what a super admin changed about a user by hand.
 * Two kinds only, not a general audit log — `complimentary_access` (until; a
 * null until means it was ended) and `partner_change` (from, to).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind');
            $table->timestamp('until')->nullable();
            $table->foreignId('from_partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->foreignId('to_partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_changes');
    }
};
