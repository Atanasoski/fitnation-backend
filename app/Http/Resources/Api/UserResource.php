<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
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
            'profile' => $this->whenLoaded('profile', function () {
                return $this->profile !== null
                    ? new UserProfileResource($this->profile)
                    : null;
            }),
            'partner' => $this->whenLoaded('partner', function () {
                if ($this->partner === null) {
                    return null;
                }

                return [
                    'id' => $this->partner->id,
                    'name' => $this->partner->name,
                    'slug' => $this->partner->slug,
                    'visual_identity' => $this->partner->identity
                        ? new PartnerVisualIdentityResource($this->partner->identity)
                        : null,
                ];
            }),
            'active_subscription' => $this->when(
                $this->relationLoaded('activeSubscription'),
                function () {
                    if ($this->activeSubscription === null) {
                        return null;
                    }

                    return new SubscriptionResource(
                        $this->activeSubscription->loadMissing(['subscriptionPlan' => fn ($q) => $q->withTrashed()])
                    );
                }
            ),
            'onboarding_completed_at' => $this->onboarding_completed_at,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
