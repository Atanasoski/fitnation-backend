<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\User;
use App\Services\Admin\AdminRoleRefused;
use App\Services\Admin\Admins;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Grant and revoke the super-admin and partner-admin roles, from the Admins
 * section of System. Each redirects back to System with a flash.
 */
class AdminController extends Controller
{
    /**
     * Grant a role to an existing, non-deleted user found by exact email. A
     * partner admin needs a partner.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', Rule::exists('users', 'email')->whereNull('deleted_at')],
            'role' => ['required', Rule::in(array_keys(Admins::ROLES))],
            'partner_id' => ['nullable', 'required_if:role,'.Admins::PARTNER_ADMIN, 'integer', Rule::exists('partners', 'id')],
        ], [
            'email.exists' => 'No user has that email address.',
            'partner_id.required_if' => 'Choose the partner this partner admin manages.',
        ]);

        $user = User::query()->where('email', $data['email'])->firstOrFail();
        $role = $data['role'];
        $partner = $role === Admins::PARTNER_ADMIN ? Partner::query()->findOrFail($data['partner_id']) : null;
        $label = Admins::label($role);

        if (! Admins::grant($user, $role, $partner, $request->user())) {
            return redirect()->route('admin.system')->with('success', "{$user->name} is already a {$label}. Nothing changed.");
        }

        return redirect()->route('admin.system')->with('success', "{$user->name} is now a {$label}".($partner ? " for {$partner->name}." : '.'));
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
