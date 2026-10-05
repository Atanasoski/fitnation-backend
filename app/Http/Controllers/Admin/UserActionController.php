<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\User;
use App\Services\Admin\UserChanges;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The super admin's actions on one user, from the user page. Each is confirmed
 * in the browser first and redirects back to the user page with a flash.
 * Staff accounts are not users and 404, as on the page itself.
 */
class UserActionController extends Controller
{
    /**
     * Grant or extend Complimentary Access. The date is a calendar day, and
     * access runs to the end of it.
     */
    public function grantComplimentaryAccess(Request $request, User $user): RedirectResponse
    {
        $this->ensureAppUser($user);

        $data = $request->validate([
            'until' => ['required', 'date', 'after:today'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $until = Carbon::parse($data['until'])->endOfDay();
        UserChanges::grantComplimentaryAccess($user, $until, $data['reason'], $request->user());

        return $this->backToUser($user, 'Complimentary Access until '.$until->format('j M Y').'.');
    }

    public function endComplimentaryAccess(Request $request, User $user): RedirectResponse
    {
        $this->ensureAppUser($user);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        UserChanges::endComplimentaryAccess($user, $data['reason'] ?? null, $request->user());

        return $this->backToUser($user, 'Complimentary Access ended.');
    }

    /**
     * Move the user to another partner. Only an active partner can take them,
     * and it must be a different one.
     */
    public function changePartner(Request $request, User $user): RedirectResponse
    {
        $this->ensureAppUser($user);

        $data = $request->validate([
            'partner_id' => [
                'required',
                'integer',
                Rule::exists('partners', 'id')->where('is_active', true),
                Rule::notIn(array_filter([$user->partner_id])),
            ],
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'partner_id.exists' => 'Choose an active partner.',
            'partner_id.not_in' => 'The user already belongs to that partner.',
        ]);

        $to = Partner::query()->findOrFail($data['partner_id']);
        UserChanges::changePartner($user, $to, $data['reason'], $request->user());

        return $this->backToUser($user, "Moved to {$to->name}.");
    }

    private function ensureAppUser(User $user): void
    {
        abort_unless(User::query()->appUsers()->withTrashed()->whereKey($user->getKey())->exists(), 404);
    }

    private function backToUser(User $user, string $message): RedirectResponse
    {
        return redirect()->route('admin.users.show', $user)->with('success', $message);
    }
}
