<?php

namespace App\Services\Admin;

use App\Enums\AdminChangeKind;
use App\Models\AdminChange;
use App\Models\Partner;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What a super admin changes about a user by hand: Complimentary Access
 * (grant, extend, end now) and the user's partner. Every change writes the
 * user and an admin change record together, so the "Grants & partner
 * changes" history can never miss one.
 *
 * Validation (a future date, a reason, an active target partner) belongs to
 * the HTTP request; this module only applies a change it is given.
 */
final class UserChanges
{
    /**
     * Grant Complimentary Access until $until, or move an existing grant's
     * end date (extend). Stored as users.grace_period_ends_at.
     */
    public static function grantComplimentaryAccess(User $user, CarbonInterface $until, string $reason, User $admin): AdminChange
    {
        return DB::transaction(function () use ($user, $until, $reason, $admin) {
            $user->forceFill(['grace_period_ends_at' => $until])->save();

            return self::record($user, $admin, AdminChangeKind::ComplimentaryAccess, $reason, ['until' => $until]);
        });
    }

    /**
     * End Complimentary Access now. Recorded with a null until.
     */
    public static function endComplimentaryAccess(User $user, ?string $reason, User $admin): AdminChange
    {
        return DB::transaction(function () use ($user, $reason, $admin) {
            $user->forceFill(['grace_period_ends_at' => null])->save();

            return self::record($user, $admin, AdminChangeKind::ComplimentaryAccess, $reason, ['until' => null]);
        });
    }

    public static function changePartner(User $user, Partner $to, string $reason, User $admin): AdminChange
    {
        return DB::transaction(function () use ($user, $to, $reason, $admin) {
            $from = $user->partner_id;
            $user->forceFill(['partner_id' => $to->getKey()])->save();
            $user->unsetRelation('partner');

            return self::record($user, $admin, AdminChangeKind::PartnerChange, $reason, [
                'from_partner_id' => $from,
                'to_partner_id' => $to->getKey(),
            ]);
        });
    }

    /**
     * The user's admin change record, newest first, with who made each change
     * and the partners involved.
     *
     * @return Collection<int, AdminChange>
     */
    public static function history(User $user): Collection
    {
        return AdminChange::query()
            ->where('user_id', $user->getKey())
            ->with(['admin:id,name', 'fromPartner:id,name', 'toPartner:id,name'])
            ->latest()
            ->latest('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function record(User $user, User $admin, AdminChangeKind $kind, ?string $reason, array $values): AdminChange
    {
        return AdminChange::query()->create([
            'user_id' => $user->getKey(),
            'admin_id' => $admin->getKey(),
            'kind' => $kind,
            'reason' => $reason,
            ...$values,
        ]);
    }
}
