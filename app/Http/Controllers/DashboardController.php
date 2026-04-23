<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WorkoutSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

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

        $timezone = config('app.timezone');
        $nowInTz = Carbon::now($timezone);

        $expiringFilter = request()->boolean('expiring');

        $filterState = $this->resolvePartnerDashboardActivityFilters($partner, $timezone, $nowInTz, $expiringFilter);

        $recentSubscriptions = Subscription::query()
            ->forPartnerUsers($partner->id)
            ->with(['user', 'subscriptionPlan' => fn ($q) => $q->withTrashed()])
            ->when(
                $filterState['plan_id'],
                fn ($q, int $planId) => $q->where('subscription_plan_id', $planId)
            )
            ->when(
                $expiringFilter,
                fn ($q) => $q->active()
                    ->whereNotNull('ends_at')
                    ->whereBetween('ends_at', [now(), now()->addDays(7)])
            )
            ->when(
                ! $expiringFilter,
                fn ($q) => $q->whereBetween('created_at', [
                    $filterState['range_start'],
                    $filterState['range_end'],
                ])
            )
            ->orderByDesc('updated_at')
            ->paginate(10)
            ->withQueryString();

        $partnerPlans = SubscriptionPlan::query()
            ->where('partner_id', $partner->id)
            ->orderBy('name')
            ->get();

        return view('dashboard.partner', [
            'partner' => $partner,
            'activeSubscriptionsCount' => $activeSubscriptionsCount,
            'monthlyRecurringRevenue' => $monthlyRecurringRevenue,
            'expiringSoonCount' => $expiringSoonCount,
            'recentSubscriptions' => $recentSubscriptions,
            'partnerPlans' => $partnerPlans,
            'activityFilters' => $filterState,
            'expiringFilter' => $expiringFilter,
        ]);
    }

    /**
     * @return array{
     *     range_start: Carbon,
     *     range_end: Carbon,
     *     plan_id: int|null,
     *     start_date_input: string,
     *     end_date_input: string,
     *     plan_id_input: int|null,
     * }
     */
    private function resolvePartnerDashboardActivityFilters(
        Partner $partner,
        string $timezone,
        Carbon $nowInTz,
        bool $expiringFilter
    ): array {
        $defaults = $this->defaultPartnerDashboardActivityRange($nowInTz);

        $filterInput = request()->only(['start_date', 'end_date', 'plan_id']);
        foreach (['start_date', 'end_date', 'plan_id'] as $key) {
            if (($filterInput[$key] ?? null) === '') {
                $filterInput[$key] = null;
            }
        }

        $validator = Validator::make($filterInput, [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'plan_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            session()->now('warning', 'Invalid filters were reset to the default date range.');

            return $this->partnerDashboardFilterPayload(
                $defaults[0],
                $defaults[1],
                null,
                $defaults[0]->toDateString(),
                $defaults[1]->toDateString(),
                null,
            );
        }

        /** @var array{start_date: string|null, end_date: string|null, plan_id: int|null} $data */
        $data = $validator->validated();

        $planId = isset($data['plan_id']) ? (int) $data['plan_id'] : null;
        if ($planId !== null) {
            $planBelongs = SubscriptionPlan::query()
                ->where('partner_id', $partner->id)
                ->whereKey($planId)
                ->exists();
            if (! $planBelongs) {
                session()->now('warning', 'That subscription plan is not available for your gym.');
                $planId = null;
            }
        }

        try {
            [$rangeStart, $rangeEnd] = $this->parsePartnerDashboardDateRangeInputs(
                $data['start_date'] ?? null,
                $data['end_date'] ?? null,
                $timezone,
                $nowInTz,
                $expiringFilter
            );
        } catch (\InvalidArgumentException) {
            session()->now('warning', 'Invalid date range was reset to the default (this month).');

            return $this->partnerDashboardFilterPayload(
                $defaults[0],
                $defaults[1],
                $planId,
                $defaults[0]->toDateString(),
                $defaults[1]->toDateString(),
                $planId,
            );
        }

        return $this->partnerDashboardFilterPayload(
            $rangeStart,
            $rangeEnd,
            $planId,
            $rangeStart->toDateString(),
            $rangeEnd->toDateString(),
            $planId,
        );
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function defaultPartnerDashboardActivityRange(Carbon $nowInTz): array
    {
        return [
            $nowInTz->copy()->startOfMonth()->startOfDay(),
            $nowInTz->copy()->endOfDay(),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     *
     * @throws \InvalidArgumentException
     */
    private function parsePartnerDashboardDateRangeInputs(
        ?string $startDate,
        ?string $endDate,
        string $timezone,
        Carbon $nowInTz,
        bool $expiringFilter
    ): array {
        if ($expiringFilter) {
            return $this->defaultPartnerDashboardActivityRange($nowInTz);
        }

        $startEmpty = $startDate === null || $startDate === '';
        $endEmpty = $endDate === null || $endDate === '';

        if ($startEmpty && $endEmpty) {
            return $this->defaultPartnerDashboardActivityRange($nowInTz);
        }

        if ($startEmpty) {
            $end = Carbon::parse((string) $endDate, $timezone)->endOfDay();

            return [
                $end->copy()->startOfMonth()->startOfDay(),
                $end,
            ];
        }

        $start = Carbon::parse((string) $startDate, $timezone)->startOfDay();

        if ($endEmpty) {
            $end = $nowInTz->copy()->endOfDay();
        } else {
            $end = Carbon::parse((string) $endDate, $timezone)->endOfDay();
        }

        if ($start->greaterThan($end)) {
            throw new \InvalidArgumentException('start_after_end');
        }

        return [$start, $end];
    }

    /**
     * @return array{
     *     range_start: Carbon,
     *     range_end: Carbon,
     *     plan_id: int|null,
     *     start_date_input: string,
     *     end_date_input: string,
     *     plan_id_input: int|null,
     * }
     */
    private function partnerDashboardFilterPayload(
        Carbon $rangeStart,
        Carbon $rangeEnd,
        ?int $planId,
        string $startDateInput,
        string $endDateInput,
        ?int $planIdInput
    ): array {
        return [
            'range_start' => $rangeStart,
            'range_end' => $rangeEnd,
            'plan_id' => $planId,
            'start_date_input' => $startDateInput,
            'end_date_input' => $endDateInput,
            'plan_id_input' => $planIdInput,
        ];
    }
}
