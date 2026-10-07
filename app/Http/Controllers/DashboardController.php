<?php

namespace App\Http\Controllers;

use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->hasRole('admin')) {
            return redirect()->route('admin.overview');
        }

        if ($user->hasRole('partner_admin')) {
            return $this->partnerDashboard();
        }

        // Users should not access web dashboard - use mobile app only
        abort(403, 'This portal is for gym administrators only. Please use the Fit Nation mobile app.');
    }

    /**
     * Partner admin dashboard with gym-specific metrics.
     */
    private function partnerDashboard()
    {
        $user = auth()->user();
        $partner = $user->partner;

        if (! $partner) {
            abort(403, 'No partner associated with your account.');
        }

        // Gym stats - app users only, staff never count
        $totalMembers = $partner->users()
            ->appUsers()
            ->count();

        $activeMembersThisWeek = $partner->users()
            ->appUsers()
            ->whereHas('workoutSessions', function ($query) {
                $query->whereBetween('performed_at', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek(),
                ]);
            })
            ->count();

        // Top active users
        $topMembers = $partner->users()
            ->appUsers()
            ->withCount(['workoutSessions' => function ($query) {
                $query->whereBetween('performed_at', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek(),
                ]);
            }])
            ->get()
            ->filter(function ($user) {
                return $user->workout_sessions_count > 0;
            })
            ->sortByDesc('workout_sessions_count')
            ->take(5)
            ->values();

        // Recent users
        $recentMembers = $partner->users()
            ->appUsers()
            ->latest()
            ->take(10)
            ->get();

        return view('dashboard.partner', compact(
            'partner',
            'totalMembers',
            'activeMembersThisWeek',
            'topMembers',
            'recentMembers'
        ));
    }
}
