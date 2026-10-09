<?php

namespace App\Services\Admin;

use App\Enums\AdminChangeKind;
use App\Enums\FreeAccessKind;
use App\Models\AdminChange;
use App\Models\Partner;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What a super admin changes about a user by hand: Complimentary Access
 * (grant, extend, end now), the user's partner, and deactivating or
 * restoring the account. Access and partner changes write the user and an
 * admin change record together, so the "Grants & partner changes" history
 * can never miss one.
 *
 * Validation (a future date, a reason, an active target partner) belongs to
 * the HTTP request; this module only applies a change it is given.
 */
final class UserChanges
{
    /**
     * Grant Complimentary Access until $until, or move an existing grant's
     * end date (extend). Stored as users.grace_period_ends_at with
     * free_access_kind `complimentary`; a grant over a running Signup Trial
     * replaces it, so the label follows the latest reason.
     */
    public static function grantComplimentaryAccess(User $user, CarbonInterface $until, string $reason, User $admin): AdminChange
    {
        return DB::transaction(function () use ($user, $until, $reason, $admin) {
            $user->grantFreeAccess(FreeAccessKind::Complimentary, $until);

            return self::record($user, $admin, AdminChangeKind::ComplimentaryAccess, $reason, ['until' => $until]);
        });
    }

    /**
     * End Complimentary Access now. Recorded with a null until. The kind
     * stays, so the account still counts as having had free access and never
     * gets a Signup Trial after it (User::startSignupTrial).
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
     * Deactivate: soft-delete the account so it can be restored, and sign it
     * out everywhere — revoking its tokens also ends its Devices (ADR-0003),
     * so nothing is pushed to a deactivated user. Nothing is anonymised,
     * unlike a user deleting their own account.
     */
    public static function deactivate(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });
    }

    /**
     * Undo a deactivation. The user signs in again to get a new session.
     */
    public static function restore(User $user): void
    {
        $user->restore();
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
