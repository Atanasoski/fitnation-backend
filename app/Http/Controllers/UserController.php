<?php

namespace App\Http\Controllers;

use App\Enums\PlanType;
use App\Enums\WorkoutSessionStatus;
use App\Http\Requests\InviteUserRequest;
use App\Mail\UserInvitationMail;
use App\Models\Partner;
use App\Models\User;
use App\Models\UserInvitation;
use App\Models\WorkoutSession;
use App\Services\FitnessMetricsService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $partner = Partner::with('identity')->findOrFail($user->partner_id);

        // Get all users of this partner with basic stats
        $users = $partner->users()
            ->with('profile')
            ->withCount([
                'workoutSessions as total_workouts',
                'plans as total_plans',
            ])
            ->with(relations: 'activeProgram')
            ->latest()
            ->paginate(15);

        return view('users.index', compact('partner', 'users'));
    }

    /**
     * Display the specified user.
     */
    public function show(Request $request, User $user): View
    {
        $currentUser = $request->user();

        // Ensure the user belongs to the trainer's partner
        if ($user->partner_id !== $currentUser->partner_id) {
            abort(403, 'Unauthorized.');
        }

        $partner = Partner::with('identity')->findOrFail($currentUser->partner_id);

        // Load user with relationships
        $user->load([
            'profile',
            'plans' => function ($query) {
                $query->withCount('workoutTemplates')
                    ->latest();
            },
        ]);

        // Get additional stats
        $totalWorkouts = $user->workoutSessions()->count();
        $completedWorkouts = $user->workoutSessions()->where('status', WorkoutSessionStatus::Completed)->count();
        $activePlan = $user->plans()->where('type', PlanType::Program)->where('is_active', true)->first();
        $lastWorkout = $user->workoutSessions()->where('status', WorkoutSessionStatus::Completed)->latest('performed_at')->first();

        // Get recent workout sessions for pagination
        $recentWorkouts = $user->workoutSessions()
            ->with('workoutTemplate')
            ->where('status', WorkoutSessionStatus::Completed)
            ->latest('performed_at')
            ->paginate(7);

        // Get fitness metrics for the user
        $fitnessMetricsService = new FitnessMetricsService($user);
        $fitnessMetrics = $fitnessMetricsService->getMetrics();

        // Get weekly workout frequency data for chart (last 12 weeks)
        $weeklyWorkoutData = $this->getWeeklyWorkoutFrequency($user, 12);

        $profile = $user->profile;
        $completionRate = $totalWorkouts > 0 ? (int) round(($completedWorkouts / $totalWorkouts) * 100) : null;
        $weeklyWorkouts = $fitnessMetrics['weekly_progress']['current_week_workouts'] ?? null;
        $weeklyGoal = $profile?->training_days_per_week ?: null;
        $latestPlans = $user->plans()->where('type', PlanType::Program)->withCount('workoutTemplates')->latest()->limit(3)->get();

        return view('users.show', compact(
            'partner',
            'user',
            'profile',
            'totalWorkouts',
            'completedWorkouts',
            'completionRate',
            'activePlan',
            'lastWorkout',
            'recentWorkouts',
            'fitnessMetrics',
            'weeklyWorkoutData',
            'weeklyWorkouts',
            'weeklyGoal',
            'latestPlans',
        ));
    }

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
     * Get weekly workout frequency data for the last N weeks.
     */
    private function getWeeklyWorkoutFrequency(User $user, int $weeks = 12): array
    {
        $endDate = Carbon::now()->endOfWeek(); // End of current week (Sunday)
        $startDate = Carbon::now()->subWeeks($weeks - 1)->startOfWeek(); // Start of N weeks ago (Monday)

        // Get all completed workouts in the date range
        $workouts = WorkoutSession::where('user_id', $user->id)
            ->where('status', WorkoutSessionStatus::Completed)
            ->whereBetween('performed_at', [$startDate, $endDate])
            ->get(['performed_at']);

        // Group by week manually to avoid MySQL-specific functions
        $weeklyCounts = [];
        foreach ($workouts as $workout) {
            $weekStart = Carbon::parse($workout->performed_at)->startOfWeek()->format('Y-m-d');
            if (! isset($weeklyCounts[$weekStart])) {
                $weeklyCounts[$weekStart] = 0;
            }
            $weeklyCounts[$weekStart]++;
        }

        // Build complete array for last N weeks with zeros for weeks without workouts
        $result = [];
        $currentWeekStart = $startDate->copy();

        for ($i = 0; $i < $weeks; $i++) {
            $weekKey = $currentWeekStart->format('Y-m-d');
            $weekLabel = $currentWeekStart->format('M d');

            $result[] = [
                'week' => $weekKey,
                'label' => $weekLabel,
                'count' => $weeklyCounts[$weekKey] ?? 0,
            ];

            $currentWeekStart->addWeek();
        }

        return $result;
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
