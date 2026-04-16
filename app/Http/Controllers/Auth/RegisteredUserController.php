<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $invitation = null;
        $partner = null;

        // Check if there's an invitation token
        if ($request->has('invitation')) {
            $invitation = UserInvitation::with('partner.identity')
                ->where('token', $request->invitation)
                ->first();

            // Validate invitation
            if ($invitation && $invitation->isValid()) {
                $partner = $invitation->partner;
            } elseif ($invitation && $invitation->isAccepted()) {
                return redirect()->route('register')
                    ->with('error', 'This invitation has already been used.')
                    ->with('alert_title', 'Invitation issue');
            } elseif ($invitation && $invitation->isExpired()) {
                return redirect()->route('register')
                    ->with('error', 'This invitation has expired. Please contact your gym for a new invitation.')
                    ->with('alert_title', 'Invitation issue');
            } elseif (! $invitation) {
                // Invitation not found - likely cancelled or never existed
                return redirect()->route('register')
                    ->with('error', 'This invitation is no longer valid. It may have been cancelled by your gym. Please contact them for assistance.')
                    ->with('alert_title', 'Invitation issue');
            } else {
                return redirect()->route('register')
                    ->with('error', 'Invalid invitation link.')
                    ->with('alert_title', 'Invitation issue');
            }
        }

        return view('auth.register', compact('invitation', 'partner'));
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $invitation = null;

        // Check if there's an invitation token
        if ($request->has('invitation_token')) {
            $invitation = UserInvitation::where('token', $request->invitation_token)->first();

            // Validate invitation still valid
            if (! $invitation || ! $invitation->isValid()) {
                return redirect()->route('register')
                    ->with('error', 'The invitation is no longer valid.')
                    ->with('alert_title', 'Invitation issue');
            }
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // If there's an invitation, verify email matches
        if ($invitation && $request->email !== $invitation->email) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['email' => 'This email does not match the invitation.']);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'partner_id' => $invitation ? $invitation->partner_id : null,
        ]);

        // Mark invitation as accepted
        if ($invitation) {
            $invitation->markAsAccepted();
        }

        event(new Registered($user));

        // Don't log users into web panel - they should use the mobile app
        if ($invitation) {
            return redirect()->route('registration.success')
                ->with('email', $user->email)
                ->with('partner', $invitation->partner->name);
        }

        // For non-invitation signups (shouldn't happen, but fallback)
        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
