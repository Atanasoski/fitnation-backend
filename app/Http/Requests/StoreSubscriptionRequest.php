<?php

namespace App\Http\Requests;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasRole('partner_admin') && $user->partner_id !== null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'redirect_after' => ['nullable', 'string', Rule::in(['users', 'member_subscriptions'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'Please select a member.',
            'subscription_plan_id.required' => 'Please select a subscription plan.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $authUser = $this->user();
            if ($authUser === null || ! $authUser->partner_id) {
                return;
            }

            $memberId = $this->integer('user_id');
            $planId = $this->integer('subscription_plan_id');

            $member = User::query()->find($memberId);
            if ($member === null || (int) $member->partner_id !== (int) $authUser->partner_id) {
                $validator->errors()->add('user_id', 'The selected member is not part of your gym.');
            } elseif (Subscription::query()
                ->where('user_id', $memberId)
                ->whereNull('cancelled_at')
                ->where('starts_at', '>', now())
                ->exists()) {
                $validator->errors()->add(
                    'user_id',
                    'This member already has a queued subscription. Cancel it first before assigning a new one.'
                );
            }

            $plan = SubscriptionPlan::query()->find($planId);
            if ($plan === null || (int) $plan->partner_id !== (int) $authUser->partner_id) {
                $validator->errors()->add('subscription_plan_id', 'The selected plan is not part of your gym.');
            }
        });
    }
}
