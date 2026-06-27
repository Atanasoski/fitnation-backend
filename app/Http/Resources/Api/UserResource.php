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
            'has_password' => !is_null($this->password),
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
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
