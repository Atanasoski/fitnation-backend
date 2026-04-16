<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubscriptionRequest;
use App\Models\Partner;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function indexForUser(Request $request, User $user): View
    {
        $admin = $this->partnerAdmin($request);

        if ((int) $user->partner_id !== (int) $admin->partner_id) {
            abort(403, 'Unauthorized.');
        }

        $subscriptions = $user->subscriptions()
            ->with(['subscriptionPlan' => fn ($q) => $q->withTrashed()])
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get();

        return view('subscriptions.user-index', compact('user', 'subscriptions'));
    }

    public function create(Request $request): View
    {
        $user = $this->partnerAdmin($request);

        $partner = Partner::query()->with('identity')->findOrFail($user->partner_id);

        $plans = SubscriptionPlan::query()
            ->where('partner_id', $user->partner_id)
            ->where('is_active', true)
            ->orderBy('price')
            ->get();

        $memberActiveSubscription = null;
        $memberUserId = $request->query('user_id');
        if ($memberUserId !== null && $memberUserId !== '') {
            $member = User::query()
                ->where('partner_id', $user->partner_id)
                ->whereKey((int) $memberUserId)
                ->first();
            if ($member !== null) {
                $memberActiveSubscription = $member->activeSubscription()->first();
            }
        }

        $redirectAfter = $request->query('redirect_after', 'users');
        if (! in_array($redirectAfter, ['users', 'member_subscriptions'], true)) {
            $redirectAfter = 'users';
        }

        return view('subscriptions.create', compact('partner', 'plans', 'memberActiveSubscription', 'redirectAfter'));
    }

    public function store(StoreSubscriptionRequest $request): RedirectResponse
    {
        $user = $this->partnerAdmin($request);

        $validated = $request->validated();

        $memberId = (int) $validated['user_id'];

        DB::transaction(function () use ($validated, $memberId): void {
            $plan = SubscriptionPlan::query()->findOrFail((int) $validated['subscription_plan_id']);

            $activeSubscription = Subscription::query()
                ->where('user_id', $memberId)
                ->active()
                ->first();

            if ($activeSubscription !== null && $activeSubscription->ends_at !== null) {
                $startsAt = Carbon::parse($activeSubscription->ends_at);
            } else {
                $startsAt = Carbon::now();
            }

            $endsAt = $plan->periodEndsAt($startsAt);

            Subscription::query()->create([
                'user_id' => $memberId,
                'subscription_plan_id' => (int) $validated['subscription_plan_id'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        $member = User::query()->findOrFail($memberId);

        if (($validated['redirect_after'] ?? 'users') === 'member_subscriptions') {
            return redirect()->route('users.subscriptions.index', $member)
                ->with('success', 'Subscription assigned.');
        }

        return redirect()->route('users.index')
            ->with('success', 'Subscription assigned.');
    }

    public function destroy(Request $request, Subscription $subscription): RedirectResponse
    {
        $user = $this->partnerAdmin($request);
        $this->ensureOwnSubscription($subscription, $user->partner_id);

        $subscription->loadMissing('user');
        $fallback = $subscription->user !== null
            ? route('users.subscriptions.index', $subscription->user)
            : route('users.index');

        if ($subscription->cancelled_at !== null) {
            return redirect()->back(fallback: $fallback)
                ->with('error', 'This subscription is already cancelled.');
        }

        $subscription->update([
            'cancelled_at' => now(),
        ]);

        return redirect()->back(fallback: $fallback)
            ->with('success', 'Subscription cancelled.');
    }

    private function partnerAdmin(Request $request): User
    {
        $user = $request->user();
        if ($user === null || ! $user->hasRole('partner_admin') || ! $user->partner_id) {
            abort(403, 'Only partner administrators can manage subscriptions.');
        }

        return $user;
    }

    private function ensureOwnSubscription(Subscription $subscription, int $partnerId): void
    {
        $subscription->loadMissing('user');
        if (! $subscription->user || (int) $subscription->user->partner_id !== (int) $partnerId) {
            abort(403);
        }
    }
}
