<?php

namespace App\Http\Controllers;

use App\Http\Requests\InviteUserRequest;
use App\Mail\UserInvitationMail;
use App\Models\Partner;
use App\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * The invitations pages, left from the old partner-admin UserController until
 * spec 025 ticket 03 deletes invitations end to end.
 */
class UserInvitationController extends Controller
{
    /**
     * Display the user invitations management page.
     */
    public function invitationsIndex(Request $request): View
    {
        $user = $request->user();
        $partner = Partner::with('identity')->findOrFail($user->partner_id);

        // Get pending invitations
        $pendingInvitations = $partner->invitations()
            ->with('inviter')
            ->pending()
            ->latest()
            ->get();

        // Get expired invitations (last 30 days)
        $expiredInvitations = $partner->invitations()
            ->with('inviter')
            ->expired()
            ->where('created_at', '>=', now()->subDays(30))
            ->latest()
            ->get();

        return view('user-invitations.index', compact('partner', 'pendingInvitations', 'expiredInvitations'));
    }

    /**
     * Store a new user invitation.
     */
    public function invitationsStore(InviteUserRequest $request): RedirectResponse
    {
        $user = $request->user();
        $partner = Partner::with('identity')->findOrFail($user->partner_id);

        // Create the invitation
        $invitation = UserInvitation::create([
            'partner_id' => $partner->id,
            'invited_by' => $user->id,
            'email' => $request->email,
            'token' => UserInvitation::generateToken(),
            'expires_at' => now()->addDays(config('app.invitation_expiry_days', 7)),
        ]);

        // Generate signup URLs with token
        $signupUrl = route('register', ['invitation' => $invitation->token]);
        $appUrl = $this->partnerAppUrl($partner, '/register?invitation='.$invitation->token);

        // Send the invitation email
        Mail::to($invitation->email)
            ->send(new UserInvitationMail($invitation, $partner, $signupUrl, $appUrl));

        return redirect()->back()
            ->with('success', "Invitation sent to {$invitation->email}!");
    }

    /**
     * Resend an existing invitation.
     */
    public function invitationsResend(Request $request, UserInvitation $invitation): RedirectResponse
    {
        $user = $request->user();

        // Verify the invitation belongs to the user's partner
        if ($invitation->partner_id !== $user->partner_id) {
            abort(403, 'Unauthorized action.');
        }

        // Check if invitation is already accepted
        if ($invitation->isAccepted()) {
            return redirect()->back()
                ->with('error', 'This invitation has already been accepted.');
        }

        // Update expiration date
        $invitation->update([
            'expires_at' => now()->addDays(config('app.invitation_expiry_days', 7)),
        ]);

        $partner = Partner::with('identity')->findOrFail($invitation->partner_id);

        // Generate signup URLs with token
        $signupUrl = route('register', ['invitation' => $invitation->token]);
        $appUrl = $this->partnerAppUrl($partner, '/register?invitation='.$invitation->token);

        // Resend the invitation email
        Mail::to($invitation->email)
            ->send(new UserInvitationMail($invitation, $partner, $signupUrl, $appUrl));

        return redirect()->back()
            ->with('success', "Invitation resent to {$invitation->email}!");
    }

    /**
     * Cancel/delete an invitation.
     */
    public function invitationsDestroy(Request $request, UserInvitation $invitation): RedirectResponse
    {
        $user = $request->user();

        // Verify the invitation belongs to the user's partner
        if ($invitation->partner_id !== $user->partner_id) {
            abort(403, 'Unauthorized action.');
        }

        $email = $invitation->email;
        $invitation->delete();

        return redirect()->back()
            ->with('success', "Invitation to {$email} has been cancelled.");
    }

    /**
     * Build app URL with partner slug as subdomain.
     *
     * Examples:
     * - Base: http://localhost:5173 -> http://localhost:5173 (no subdomain for localhost)
     * - Base: https://fitnation.mk -> https://synergy.fitnation.mk
     */
    private function partnerAppUrl(Partner $partner, string $path = ''): string
    {
        $base = config('app.webapp_url', 'http://localhost:5173');
        $parsed = parse_url($base);
        $host = $parsed['host'] ?? 'localhost';

        if (in_array($host, ['localhost', '127.0.0.1'])) {
            return rtrim($base, '/').$path;
        }

        return ($parsed['scheme'] ?? 'https').'://'.$partner->slug.'.'.$host.$path;
    }
}
