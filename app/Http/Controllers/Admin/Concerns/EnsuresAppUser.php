<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\User;

/**
 * Staff accounts are not users: every admin page or action about one user
 * 404s for them, deleted users included.
 */
trait EnsuresAppUser
{
    private function ensureAppUser(User $user): void
    {
        abort_unless(User::query()->appUsers()->withTrashed()->whereKey($user->getKey())->exists(), 404);
    }
}
