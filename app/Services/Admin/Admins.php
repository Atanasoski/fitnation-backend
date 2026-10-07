<?php

namespace App\Services\Admin;

use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * The staff roles: super admins (role `admin`), who sign in to the web panel,
 * and partner admins (role `partner_admin`), dormant since spec 025: no web
 * login, kept so partner dashboard access can come back. Lists both and
 * revokes either; grants super admin only.
 *
 * The role_user row's timestamps are the record of a grant: no Admin Change
 * is written (Admin Change stays two kinds, CONTEXT.md). Each grant and
 * revoke is logged at info level with who did it.
 *
 * A granted user becomes staff, so User::appUsers() drops them from every
 * app-user count. Finding the user (an existing, non-deleted account by exact
 * email) belongs to the HTTP request; this module applies a change it is
 * given.
 */
final class Admins
{
    public const SUPER_ADMIN = 'admin';

    public const PARTNER_ADMIN = 'partner_admin';

    /** Role slug => label, in list order: what the list shows and revoke accepts. */
    public const ROLES = [
        self::SUPER_ADMIN => 'Super admin',
        self::PARTNER_ADMIN => 'Partner admin',
    ];

    /** The roles grant() accepts. */
    public const GRANTABLE = [self::SUPER_ADMIN];

    /**
     * Every non-deleted super admin and partner admin, one row per role held:
     * super admins first, then partner admins, each oldest grant first.
     * `partner` is the partner a partner admin manages (null for a super
     * admin); `since` is the role row's created_at, null for rows granted
     * before the relation stamped them.
     *
     * @return list<array{user: User, role: string, partner: ?Partner, since: ?CarbonImmutable}>
     */
    public static function list(): array
    {
        $grants = DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->whereIn('roles.slug', array_keys(self::ROLES))
            ->get(['role_user.user_id', 'roles.slug', 'role_user.created_at', 'role_user.id']);

        $users = User::query()->with('partner')->findMany($grants->pluck('user_id')->unique())->keyBy('id');
        $order = array_flip(array_keys(self::ROLES));

        return $grants
            ->filter(fn ($grant) => $users->has($grant->user_id))
            ->sortBy([
                fn ($a, $b) => $order[$a->slug] <=> $order[$b->slug],
                fn ($a, $b) => [$a->created_at ?? '', $a->id] <=> [$b->created_at ?? '', $b->id],
            ])
            ->map(fn ($grant) => [
                'user' => $users[$grant->user_id],
                'role' => $grant->slug,
                'partner' => $grant->slug === self::PARTNER_ADMIN ? $users[$grant->user_id]->partner : null,
                'since' => $grant->created_at === null ? null : CarbonImmutable::parse($grant->created_at),
            ])
            ->values()
            ->all();
    }

    /** The role in a sentence: "super admin", "partner admin". */
    public static function label(string $role): string
    {
        return strtolower(self::ROLES[$role]);
    }

    /**
     * Grant $role (one of GRANTABLE) to $user. Returns false, changing
     * nothing, when the user already has the role.
     */
    public static function grant(User $user, string $role, User $by): bool
    {
        if (! in_array($role, self::GRANTABLE, true)) {
            throw new InvalidArgumentException("The {$role} role cannot be granted.");
        }

        if ($user->hasRole($role)) {
            return false;
        }

        $user->roles()->attach(self::role($role)->getKey());

        Log::info('Admin role granted', [
            'role' => $role,
            'user_id' => $user->getKey(),
            'by_user_id' => $by->getKey(),
        ]);

        return true;
    }

    /**
     * Revoke $role from $user. Revoking partner admin leaves users.partner_id
     * as it is. The user must have the role.
     *
     * @throws AdminRoleRefused for your own super-admin role, or the last
     *                          remaining (non-deleted) super admin
     */
    public static function revoke(User $user, string $role, User $by): void
    {
        if ($role === self::SUPER_ADMIN) {
            if ($user->is($by)) {
                throw new AdminRoleRefused('You cannot revoke your own super-admin role.');
            }

            if (User::query()->whereHas('roles', fn ($roles) => $roles->where('slug', self::SUPER_ADMIN))->count() <= 1) {
                throw new AdminRoleRefused("{$user->name} is the last super admin, so the role stays.");
            }
        }

        $user->roles()->detach(self::role($role)->getKey());

        Log::info('Admin role revoked', [
            'role' => $role,
            'user_id' => $user->getKey(),
            'by_user_id' => $by->getKey(),
        ]);
    }

    private static function role(string $slug): Role
    {
        return Role::query()->firstOrCreate(['slug' => $slug], ['name' => self::ROLES[$slug]]);
    }
}
