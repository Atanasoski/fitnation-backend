<?php

use App\Models\Partner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every user belongs to a partner: anyone who joined without a gym belongs to
 * the House Partner. Admin and partner-admin accounts keep none. Soft-deleted
 * users move too, so history matches the rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        $houseId = Partner::houseId();

        $partnerless = DB::table('users')
            ->whereNull('partner_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('role_user')
                    ->join('roles', 'roles.id', '=', 'role_user.role_id')
                    ->whereColumn('role_user.user_id', 'users.id')
                    ->whereIn('roles.slug', ['admin', 'partner_admin']);
            });

        // A fresh database has no users yet and needs no House Partner; one
        // with partnerless users must not "succeed" while leaving them so.
        if (! $partnerless->clone()->exists()) {
            return;
        }

        if (! DB::table('partners')->where('id', $houseId)->exists()) {
            throw new \RuntimeException("The House Partner (id {$houseId}, HOUSE_PARTNER_ID) does not exist; create it before migrating.");
        }

        $partnerless->update(['partner_id' => $houseId]);
    }

    /**
     * Not reversible: which users had no partner before is not recorded.
     */
    public function down(): void
    {
        //
    }
};
