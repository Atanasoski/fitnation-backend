<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Why a user has free access until grace_period_ends_at: `signup_trial` or
 * `complimentary` (App\Enums\FreeAccessKind; spec 026 ticket 05). Every date
 * set before this column existed was a test or admin grant, so those rows are
 * backfilled `complimentary` — and so are users whose admin grant was since
 * ended, so they never get a Signup Trial after it (once per account).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('free_access_kind', 32)->nullable()->after('grace_period_ends_at');
        });

        DB::table('users')
            ->whereNotNull('grace_period_ends_at')
            ->orWhereIn('id', DB::table('admin_changes')->select('user_id')->where('kind', 'complimentary_access'))
            ->update(['free_access_kind' => 'complimentary']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('free_access_kind');
        });
    }
};
