<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            // The partner's Paywall Discount in percent (10, 20, 30 or 50; see
            // PaywallDiscountTier). Null: its members see the standard price.
            $table->unsignedTinyInteger('paywall_discount_tier')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn('paywall_discount_tier');
        });
    }
};
