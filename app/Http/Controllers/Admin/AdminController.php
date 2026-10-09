<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AdminRoleRefused;
use App\Services\Admin\Admins;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Grant the super-admin role and revoke either staff role, from the Admins
 * section of System. Each redirects back to System with a flash.
 */
class AdminController extends Controller
{
    /**
     * Grant a role (super admin only) to an existing, non-deleted user found
     * by exact email.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', Rule::exists('users', 'email')->whereNull('deleted_at')],
            'role' => ['required', Rule::in(Admins::GRANTABLE)],
        ], [
            'email.exists' => 'No user has that email address.',
        ]);

        $user = User::query()->where('email', $data['email'])->firstOrFail();
        $role = $data['role'];
        $label = Admins::label($role);

        if (! Admins::grant($user, $role, $request->user())) {
            return redirect()->route('admin.system')->with('success', "{$user->name} is already a {$label}. Nothing changed.");
        }

        return redirect()->route('admin.system')->with('success', "{$user->name} is now a {$label}.");
    }

    /**
     * Revoke a role, confirmed in the browser first. Refusals (your own
     * super-admin role, the last super admin) come back as an error.
     */
    public function destroy(Request $request, User $user, string $role): RedirectResponse
    {
        abort_unless($user->hasRole($role), 404);

        try {
            Admins::revoke($user, $role, $request->user());
        } catch (AdminRoleRefused $refused) {
            return redirect()->route('admin.system')->withErrors(['admins' => $refused->getMessage()]);
        }

        return redirect()->route('admin.system')->with('success', "{$user->name} is no longer a ".Admins::label($role).'.');
    }
}
