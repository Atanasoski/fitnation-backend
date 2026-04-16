<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WorkoutSession;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->hasRole('admin')) {
            return $this->adminDashboard();
        }

        if ($user->hasRole('partner_admin')) {
            return $this->partnerDashboard();
        }

        // Users should not access web dashboard - use mobile app only
        abort(403, 'This portal is for gym administrators only. Please use the Fit Nation mobile app.');
    }

    /**
     * Admin dashboard with platform-wide metrics.
     */
    private function adminDashboard()
    {
        // Platform stats
        $totalPartners = Partner::count();
        $activePartners = Partner::where('is_active', true)->count();
        $totalUsers = User::count();

        // Partners with metrics
        $partners = Partner::withCount([
            'users',
            'users as active_users_count' => function ($query) {
                $query->whereHas('workoutSessions', function ($q) {
                    $q->whereBetween('performed_at', [
                        Carbon::now()->startOfWeek(),
                        Carbon::now()->endOfWeek(),
                    ]);
                });
            },
        ])
            ->with(['identity', 'users' => function ($query) {
                $query->latest()->take(1);
            }])
            ->withMax('users', 'created_at')
            ->get()
            ->map(function ($partner) {
                $partner->workouts_this_week = WorkoutSession::whereHas('user', function ($query) use ($partner) {
                    $query->where('partner_id', $partner->id);
                })->whereBetween('performed_at', [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek(),
                ])->count();

                return $partner;
            });

        // Recent activity
        $recentUsers = User::with('partner')
            ->latest()
            ->take(10)
            ->get();

        // Partner Admin Logins - Show recent partner admin logins
        $partnerActivity = User::with(['partner.identity', 'roles'])
            ->whereHas('roles', function ($query) {
                $query->where('slug', 'partner_admin');
            })
            ->whereNotNull('last_login_at')
            ->orderByDesc('last_login_at')
            ->take(10)
            ->get();

        return view('dashboard.admin', compact(
            'totalPartners',
            'activePartners',
            'totalUsers',
            'partners',
            'recentUsers',
            'partnerActivity'
        ));
    }

    /**
     * Partner admin dashboard landing.
     */
    private function partnerDashboard()
    {
        $user = auth()->user();
        $partner = $user->partner;

        if (! $partner) {
            abort(403, 'No partner associated with your account.');
        }

        $partner->loadCount(['users', 'subscriptionPlans']);

        $activeSubscriptions = Subscription::query()
            ->forPartnerUsers($partner->id)
            ->active();

        $activeSubscriptionsCount = $activeSubscriptions->count();

        $monthlyRecurringRevenue = Subscription::query()
            ->forPartnerUsers($partner->id)
            ->active()
            ->join('subscription_plans', 'subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
            ->sum('subscription_plans.price');

        $expiringSoonCount = Subscription::query()
            ->forPartnerUsers($partner->id)
            ->active()
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now(), now()->addDays(7)])
            ->count();

        $recentSubscriptions = Subscription::query()
            ->forPartnerUsers($partner->id)
            ->with(['user', 'subscriptionPlan' => fn ($q) => $q->withTrashed()])
            ->orderByDesc('updated_at')
            ->paginate(10)
            ->withQueryString();

        return view('dashboard.partner', compact(
            'partner',
            'activeSubscriptionsCount',
            'monthlyRecurringRevenue',
            'expiringSoonCount',
            'recentSubscriptions',
        ));
    }
}
