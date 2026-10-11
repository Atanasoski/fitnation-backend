<?php

namespace App\Http\Resources\Api;

use App\Models\User;
use App\Notifications\WeeklySummary;
use App\Services\Access\AccessSources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /** What GET /user loads, so every endpoint answering with "the user" matches it. */
    public const RELATIONS = ['partner', 'profile', 'subscription'];

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'profile_photo' => asset($this->profile_photo),
            'has_password' => ! is_null($this->password),
            'profile' => $this->whenLoaded('profile', function () {
                return new UserProfileResource($this->profile);
            }),
            'partner' => $this->whenLoaded('partner', function () {
                return [
                    'id' => $this->partner->id,
                    'name' => $this->partner->name,
                    'slug' => $this->partner->slug,
                    'visual_identity' => $this->partner->identity
                        ? new PartnerVisualIdentityResource($this->partner->identity)
                        : null,
                ];
            }),
            'onboarding_completed_at' => $this->onboarding_completed_at,
            'push_enabled' => $this->push_enabled,
            'notification_settings' => [
                WeeklySummary::SETTING => $this->notificationSetting(WeeklySummary::SETTING, WeeklySummary::DEFAULT),
            ],
            'email_verified_at' => $this->email_verified_at,
            'entitlements' => $this->entitlements()->map(fn ($e) => $e->value)->all(),
            'subscription' => [
                'status' => $this->subscription?->status?->value,
                'expires_at' => $this->subscription?->expires_at,
                'is_trial' => $this->subscription?->isInTrial() ?? false,
                'is_sponsored_by_gym' => $this->partner?->isSponsoringMembers() ?? false,
                'grace_period_ends_at' => $this->grace_period_ends_at,
                'free_access_kind' => $this->freeAccessKind()?->value,
                'access_source' => AccessSources::sourceOf($this->resource)->value,
                'enforced' => (bool) config('subscriptions.enforced'),
                'signup_trial_days' => User::signupTrialDays(),
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
