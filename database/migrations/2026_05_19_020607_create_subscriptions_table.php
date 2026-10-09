<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('partner_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('product_id');
            $table->enum('store', ['app_store', 'play_store']);
            $table->enum('status', ['active', 'cancelled', 'expired', 'billing_issue', 'paused']);
            $table->enum('period_type', ['normal', 'trial', 'intro', 'promotional']);

            $table->decimal('price', 10, 2)->nullable();
            $table->char('currency', 3)->nullable();

            $table->timestamp('purchased_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->enum('environment', ['sandbox', 'production']);

            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
