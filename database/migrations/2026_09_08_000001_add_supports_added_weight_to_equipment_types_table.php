<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether an exercise on this equipment can carry added weight is a fact about
 * the equipment, so it lives with the equipment type and the clients read it.
 * Before this, four client copies of the rule disagreed about TRX (app spec
 * 0015). The seed mirrors ProgressionCalculatorService::getWeightIncrement(),
 * which already treats BODYWEIGHT, TRX and BAND as carrying no external load.
 */
return new class extends Migration
{
    private const WITHOUT_ADDED_WEIGHT = ['BODYWEIGHT', 'TRX', 'BAND'];

    public function up(): void
    {
        Schema::table('equipment_types', function (Blueprint $table) {
            $table->boolean('supports_added_weight')->default(true)->after('display_order');
        });

        DB::table('equipment_types')
            ->whereIn('code', self::WITHOUT_ADDED_WEIGHT)
            ->update(['supports_added_weight' => false]);
    }

    public function down(): void
    {
        Schema::table('equipment_types', function (Blueprint $table) {
            $table->dropColumn('supports_added_weight');
        });
    }
};
