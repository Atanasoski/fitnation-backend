<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubscriptionPlanRequest;
use App\Http\Requests\UpdateSubscriptionPlanRequest;
use App\Models\Partner;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionPlanController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->partnerAdmin($request);

        $partner = Partner::query()->with('identity')->findOrFail($user->partner_id);

        $subscriptionPlans = SubscriptionPlan::query()
            ->where('partner_id', $user->partner_id)
            ->withCount('subscriptions')
            ->orderBy('name')
            ->get();

        return view('subscription-plans.index', compact('partner', 'subscriptionPlans'));
    }

    public function create(Request $request): View
    {
        $user = $this->partnerAdmin($request);

        $partner = Partner::query()->with('identity')->findOrFail($user->partner_id);

        return view('subscription-plans.create', compact('partner'));
    }

    public function store(StoreSubscriptionPlanRequest $request): RedirectResponse
    {
        $user = $this->partnerAdmin($request);

        $data = $request->validated();
        $data['partner_id'] = $user->partner_id;
        $data['is_active'] = $request->boolean('is_active', true);

        SubscriptionPlan::query()->create($data);

        return redirect()->route('subscription-plans.index')
            ->with('success', 'Subscription plan created.');
    }

    public function show(Request $request, SubscriptionPlan $subscriptionPlan): View
    {
        $user = $this->partnerAdmin($request);
        $this->ensureOwnPlan($subscriptionPlan, $user->partner_id);

        $partner = Partner::query()->with('identity')->findOrFail($user->partner_id);
        $subscriptionPlan->loadCount('subscriptions');

        return view('subscription-plans.show', compact('partner', 'subscriptionPlan'));
    }

    public function edit(Request $request, SubscriptionPlan $subscriptionPlan): View
    {
        $user = $this->partnerAdmin($request);
        $this->ensureOwnPlan($subscriptionPlan, $user->partner_id);

        $partner = Partner::query()->with('identity')->findOrFail($user->partner_id);

        return view('subscription-plans.edit', compact('partner', 'subscriptionPlan'));
    }

    public function update(UpdateSubscriptionPlanRequest $request, SubscriptionPlan $subscriptionPlan): RedirectResponse
    {
        $user = $this->partnerAdmin($request);
        $this->ensureOwnPlan($subscriptionPlan, $user->partner_id);

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', $subscriptionPlan->is_active);

        $subscriptionPlan->update($data);

        return redirect()->route('subscription-plans.index')
            ->with('success', 'Subscription plan updated.');
    }

    public function destroy(Request $request, SubscriptionPlan $subscriptionPlan): RedirectResponse
    {
        $user = $this->partnerAdmin($request);
        $this->ensureOwnPlan($subscriptionPlan, $user->partner_id);

        $subscriptionPlan->delete();

        return redirect()->route('subscription-plans.index')
            ->with('success', 'Subscription plan deleted.');
    }

    private function partnerAdmin(Request $request): User
    {
        $user = $request->user();
        if ($user === null || ! $user->hasRole('partner_admin') || ! $user->partner_id) {
            abort(403, 'Only partner administrators can manage subscription plans.');
        }

        return $user;
    }

    private function ensureOwnPlan(SubscriptionPlan $subscriptionPlan, int $partnerId): void
    {
        if ((int) $subscriptionPlan->partner_id !== (int) $partnerId) {
            abort(403);
        }
    }
}
