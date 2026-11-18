<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Split Focus becomes the member's own choice (#143), stored beside the
 * other four plan inputs. Nullable, like every other optional profile
 * field: a null Split Focus means Balanced.
 *
 * Deliberately no backfill. Existing profiles are written to their
 * current effective emphasis by a one-off operation run against
 * production after this ships (#144), so the schema change can be
 * deployed and rolled back on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('split_focus')->nullable()->after('training_days_per_week');
        });
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn('split_focus');
        });
    }
};
